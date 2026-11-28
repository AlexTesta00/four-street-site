<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Database;

use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Driver;
use FourStreet\Database\Migration\ColumnMigration;
use FourStreet\Database\Migration\Migration;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\TestDatabase;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Le migrazioni vere di database/migrations/ sul database dei test: SQLite, oppure MariaDB in CI.
 */
#[Group('database')]
#[CoversClass(Migrator::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Row::class)]
#[UsesClass(UtcDateTime::class)]
final class SchemaTest extends TestCase
{
    private const array MIGRATIONS = [
        '0001_create_users',
        '0002_create_sessions',
        '0003_create_account_tokens',
        '0004_create_rate_limits',
        '0005_create_bookings',
        '0006_create_schedule',
        '0007_add_bookings_gym_email',
    ];

    private Database $database;

    protected function setUp(): void
    {
        $this->database = TestDatabase::create();
    }

    public function testEveryMigrationIsAppliedAndRerunningChangesNothing(): void
    {
        $migrator = new Migrator($this->database, TestDatabase::migrations(), FrozenClock::at('2026-11-25 08:00:00'));

        self::assertSame(array_fill_keys(self::MIGRATIONS, true), $migrator->status());
        self::assertSame([], $migrator->migrate());
    }

    public function testEveryStatementCanRunTwice(): void
    {
        // Una migrazione interrotta a metà (su MySQL lo schema non è transazionale) si rilancia così com'è. Una colonna
        // aggiunta no: quella la salta il Migrator, se c'è già (il test qui sotto).
        foreach (self::MIGRATIONS as $version) {
            $migration = self::migration($version);
            if ($migration instanceof ColumnMigration) {
                continue;
            }
            foreach ($migration->statements($this->database->driver()) as $statement) {
                $this->database->execute($statement);
            }
        }

        self::assertSame(0, $this->database->fetchOne('SELECT COUNT(*) AS total FROM users')?->int('total'));
    }

    public function testAColumnMigrationInterruptedBeforeTheRegistryIsOnlyRegisteredAgain(): void
    {
        // Su MySQL l'ALTER TABLE è fatto, ma il processo si è fermato prima di scrivere il registro: rilanciata, la
        // migrazione trova la colonna e si registra soltanto, senza errori. La colonna resta con i suoi vincoli.
        $this->database->execute('DELETE FROM ' . Migrator::TABLE . " WHERE version = '0007_add_bookings_gym_email'");
        $migrator = new Migrator($this->database, TestDatabase::migrations(), FrozenClock::at('2026-11-25 08:00:00'));
        self::assertFalse($migrator->status()['0007_add_bookings_gym_email'] ?? true);

        self::assertSame(['0007_add_bookings_gym_email'], $migrator->migrate());
        self::assertSame(array_fill_keys(self::MIGRATIONS, true), $migrator->status());

        // Il CHECK della colonna c'è ancora: SQLite cita l'espressione, MySQL e MariaDB il nome del vincolo.
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/gym_email/');
        $this->insert('bookings', ['user_id' => null, 'guest_name' => 'Ospite Prova', 'slot_date' => '2026-12-07', 'slot_time' => '18:30', 'starts_at' => '2026-12-07 17:30:00', 'seat' => null, 'active' => null, 'created_at' => '2026-11-25 07:00:00', 'created_by' => 'admin', 'cancelled_at' => '2026-11-25 08:00:00', 'cancelled_by' => 'admin', 'gym_email' => 'pending']);
    }

    public function testAColumnMigrationIsASingleStatementOnMySql(): void
    {
        // Su MySQL un ALTER TABLE è atomico: se la colonna c'è, ci sono anche i suoi vincoli. Due istruzioni no.
        $columns = 0;
        foreach (self::MIGRATIONS as $version) {
            $migration = self::migration($version);
            if ($migration instanceof ColumnMigration) {
                ++$columns;
                $statements = $migration->statements(Driver::MySql);
                self::assertCount(1, $statements, $version);
                self::assertMatchesRegularExpression(
                    '/\AALTER TABLE ' . preg_quote($migration->table(), '/') . '\s+ADD COLUMN ' . preg_quote($migration->column(), '/') . '\s/',
                    $statements[0],
                    $version,
                );
            }
        }
        self::assertNotSame(0, $columns, 'Almeno una: la 0007.');
    }

    /**
     * @return iterable<string, array{string, array<string, int|string|null>}>
     */
    public static function rejectedRows(): iterable
    {
        $user = ['full_name' => 'A', 'email' => 'a@example.com', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'customer', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00'];

        yield 'ruolo sconosciuto' => ['users', [...$user, 'email' => 'b@example.com', 'role' => 'superuser']];
        yield 'email ripetuta' => ['users', $user];
        yield 'nome mancante' => ['users', [...$user, 'email' => 'c@example.com', 'full_name' => null]];
        yield 'scopo sconosciuto del link' => ['account_tokens', ['token_hash' => str_repeat('a', 64), 'user_id' => 0, 'purpose' => 'magic_link', 'created_at' => '2026-11-25 07:00:00', 'expires_at' => '2026-11-27 07:00:00']];

        $booking = ['user_id' => 0, 'slot_date' => '2026-12-07', 'slot_time' => '18:30', 'starts_at' => '2026-12-07 17:30:00', 'seat' => 1, 'active' => 1, 'created_at' => '2026-11-25 07:00:00', 'created_by' => 'customer'];
        $guest = [...$booking, 'user_id' => null, 'guest_name' => 'Ospite Prova', 'created_by' => 'admin'];
        $cancelled = ['seat' => null, 'active' => null, 'cancelled_at' => '2026-11-25 08:00:00', 'cancelled_by' => 'customer'];
        yield 'posto zero' => ['bookings', [...$booking, 'seat' => 0]];
        yield 'attiva vale solo 1' => ['bookings', [...$booking, 'active' => 2]];
        yield 'attiva senza posto' => ['bookings', [...$booking, 'seat' => null]];
        yield 'attiva con la disdetta' => ['bookings', [...$booking, 'cancelled_at' => '2026-11-25 08:00:00', 'cancelled_by' => 'customer']];
        yield 'disdetta con il posto' => ['bookings', [...$booking, ...$cancelled, 'seat' => 1]];
        yield 'disdetta senza quando' => ['bookings', [...$booking, ...$cancelled, 'cancelled_at' => null]];
        yield 'disdetta senza chi' => ['bookings', [...$booking, ...$cancelled, 'cancelled_by' => null]];
        yield 'disdetta da uno sconosciuto' => ['bookings', [...$booking, ...$cancelled, 'cancelled_by' => 'robot']];
        yield 'posto senza active né disdetta' => ['bookings', [...$booking, 'active' => null]];
        yield 'giorno mancante' => ['bookings', [...$booking, 'slot_date' => null]];
        yield 'cliente inesistente' => ['bookings', [...$booking, 'user_id' => 999_999]];
        yield 'senza chi l\'ha inserita' => ['bookings', [...$booking, 'created_by' => null]];
        yield 'inserita da uno sconosciuto' => ['bookings', [...$booking, 'created_by' => 'robot']];
        yield 'cliente e persona senza account insieme' => ['bookings', [...$booking, 'guest_name' => 'Ospite Prova']];
        yield 'né cliente né persona senza account' => ['bookings', [...$booking, 'user_id' => null]];
        yield 'persona senza account inserita da un cliente' => ['bookings', [...$guest, 'created_by' => 'customer']];
        yield 'nome di una lettera' => ['bookings', [...$guest, 'guest_name' => 'O']];
        yield 'nome troppo lungo' => ['bookings', [...$guest, 'guest_name' => str_repeat('o', 101)]];

        // L'email della palestra (0007): solo i suoi quattro stati, e solo per un cliente registrato a cui ha disdetto lei.
        $byGym = [...$booking, ...$cancelled, 'cancelled_by' => 'admin'];
        yield 'email della palestra in uno stato sconosciuto' => ['bookings', [...$byGym, 'gym_email' => 'queued']];
        yield 'email della palestra di una prenotazione attiva' => ['bookings', [...$booking, 'gym_email' => 'pending']];
        yield 'email della palestra per una disdetta del cliente' => ['bookings', [...$booking, ...$cancelled, 'gym_email' => 'sent']];
        yield 'email della palestra a chi non ha l\'account' => ['bookings', [...$guest, ...$cancelled, 'cancelled_by' => 'admin', 'gym_email' => 'failed']];

        // L'orario e le regole del pannello del titolare (0006).
        yield 'regola senza valore' => ['booking_settings', ['name' => 'days_ahead', 'value' => '', 'updated_by' => null, 'updated_at' => '2026-11-25 07:00:00']];
        $weekly = ['weekday' => 1, 'slot_time' => '18:30', 'valid_from' => null, 'valid_until' => null];
        yield 'giorno della settimana zero' => ['schedule_weekly_slots', [...$weekly, 'weekday' => 0]];
        yield 'giorno della settimana otto' => ['schedule_weekly_slots', [...$weekly, 'weekday' => 8]];
        yield 'orario con i secondi' => ['schedule_weekly_slots', [...$weekly, 'slot_time' => '18:30:00']];
        yield 'validità che finisce quando comincia' => ['schedule_weekly_slots', [...$weekly, 'valid_from' => '2026-12-08', 'valid_until' => '2026-12-08']];
        $day = ['slot_date' => '2026-12-24', 'closed' => 1, 'note' => null, 'updated_by' => null, 'updated_at' => '2026-11-25 07:00:00'];
        yield 'chiuso vale 0 o 1' => ['schedule_days', [...$day, 'closed' => 2]];
        yield 'nota vuota' => ['schedule_days', [...$day, 'note' => '']];
        yield 'nota troppo lunga' => ['schedule_days', [...$day, 'note' => str_repeat('n', 101)]];
        yield 'cambiato da un utente inesistente' => ['schedule_days', [...$day, 'updated_by' => 999_999]];
        yield 'orario di un giorno senza eccezione' => ['schedule_day_slots', ['slot_date' => '2026-12-24', 'slot_time' => '18:30']];
    }

    /**
     * @param array<string, int|string|null> $row
     */
    #[DataProvider('rejectedRows')]
    public function testTheSchemaRefusesInvalidRows(string $table, array $row): void
    {
        $this->insert('users', ['full_name' => 'A', 'email' => 'a@example.com', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'customer', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00']);
        // user_id 0 vale "l'utente appena creato": la riga è sbagliata per altro.
        if (($row['user_id'] ?? null) === 0) {
            $row['user_id'] = $this->database->lastInsertId();
        }

        $this->expectException(PDOException::class);

        $this->insert($table, $row);
    }

    public function testTheValidRowsNextToTheRejectedOnesAreAccepted(): void
    {
        $this->insert('users', ['full_name' => 'A', 'email' => 'a@example.test', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'admin', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00']);
        $booking = ['slot_date' => '2026-12-07', 'slot_time' => '18:30', 'starts_at' => '2026-12-07 17:30:00', 'active' => 1, 'created_at' => '2026-11-25 07:00:00'];

        // Un cliente, inserito da lui o dalla palestra; una persona senza account, solo dalla palestra, con un nome di 2-100 caratteri.
        $this->insert('bookings', [...$booking, 'user_id' => $this->database->lastInsertId(), 'seat' => 1, 'created_by' => 'admin']);
        $this->insert('bookings', [...$booking, 'user_id' => null, 'guest_name' => 'Lu', 'seat' => 2, 'created_by' => 'admin']);
        $this->insert('bookings', [...$booking, 'user_id' => null, 'guest_name' => str_repeat('o', 100), 'seat' => 3, 'created_by' => 'admin']);

        self::assertSame(3, $this->database->fetchOne('SELECT COUNT(*) AS total FROM bookings')?->int('total'));
    }

    public function testTheEmailOfTheGymTakesItsFourStatesAndNullForEverythingElse(): void
    {
        $this->insert('users', ['full_name' => 'A', 'email' => 'a@example.test', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'customer', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00']);
        $user = $this->database->lastInsertId();
        $byGym = ['user_id' => $user, 'slot_time' => '18:30', 'starts_at' => '2026-12-07 17:30:00', 'seat' => null, 'active' => null, 'created_at' => '2026-11-25 07:00:00', 'created_by' => 'customer', 'cancelled_at' => '2026-11-25 08:00:00', 'cancelled_by' => 'admin'];

        // Un giorno ciascuna: un cliente ha al più una prenotazione attiva al giorno, ma le disdette non contano.
        foreach (['pending', 'sending', 'sent', 'failed', null] as $index => $state) {
            $this->insert('bookings', [...$byGym, 'slot_date' => '2026-12-1' . $index, 'gym_email' => $state]);
        }
        // Senza stato anche chi non ha l'account, le disdette del cliente e le prenotazioni attive.
        $this->insert('bookings', [...$byGym, 'user_id' => null, 'guest_name' => 'Ospite Prova', 'created_by' => 'admin', 'slot_date' => '2026-12-20', 'gym_email' => null]);
        $this->insert('bookings', [...$byGym, 'cancelled_by' => 'customer', 'slot_date' => '2026-12-21', 'gym_email' => null]);
        $this->insert('bookings', [...$byGym, 'seat' => 1, 'active' => 1, 'cancelled_at' => null, 'cancelled_by' => null, 'slot_date' => '2026-12-22', 'gym_email' => null]);

        self::assertSame(
            ['failed' => 1, 'pending' => 1, 'sending' => 1, 'sent' => 1],
            array_column(array_map(
                static fn(Row $row): array => [$row->string('gym_email'), $row->int('total')],
                $this->database->fetchAll('SELECT gym_email, COUNT(*) AS total FROM bookings WHERE gym_email IS NOT NULL GROUP BY gym_email ORDER BY gym_email'),
            ), 1, 0),
        );
        self::assertSame(4, $this->database->fetchOne('SELECT COUNT(*) AS total FROM bookings WHERE gym_email IS NULL')?->int('total'));
    }

    public function testTheScheduleTablesAcceptWhatThePanelWrites(): void
    {
        $this->insert('users', ['full_name' => 'A', 'email' => 'a@example.test', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'admin', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00']);
        $admin = $this->database->lastInsertId();

        $this->insert('booking_settings', ['name' => 'schedule', 'value' => 'database', 'updated_by' => $admin, 'updated_at' => '2026-11-25 07:00:00']);
        $this->insert('schedule_weekly_slots', ['weekday' => 7, 'slot_time' => '10:00', 'valid_from' => null, 'valid_until' => '2026-11-26']);
        $this->insert('schedule_weekly_slots', ['weekday' => 7, 'slot_time' => '10:00', 'valid_from' => '2026-11-26', 'valid_until' => null]);
        $this->insert('schedule_days', ['slot_date' => '2026-12-24', 'closed' => 0, 'note' => str_repeat('n', 100), 'updated_by' => $admin, 'updated_at' => '2026-11-25 07:00:00']);
        $this->insert('schedule_day_slots', ['slot_date' => '2026-12-24', 'slot_time' => '18:30']);

        // Cancellato l'account del titolare, le sue modifiche restano, senza autore; cancellato il giorno, i suoi orari.
        $this->database->execute('DELETE FROM users WHERE id = :id', ['id' => $admin]);
        self::assertNull($this->database->fetchOne("SELECT updated_by FROM schedule_days WHERE slot_date = '2026-12-24'")?->nullableInt('updated_by'));
        self::assertNull($this->database->fetchOne("SELECT updated_by FROM booking_settings WHERE name = 'schedule'")?->nullableInt('updated_by'));
        $this->database->execute("DELETE FROM schedule_days WHERE slot_date = '2026-12-24'");
        self::assertSame(0, $this->database->fetchOne('SELECT COUNT(*) AS total FROM schedule_day_slots')?->int('total'));
    }

    public function testEmailsCompareExactly(): void
    {
        // Le email sono già in minuscolo: la colonna le confronta byte per byte, senza equivalenze di accenti.
        $this->insert('users', ['full_name' => 'A', 'email' => 'jose@example.com', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'customer', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00']);
        $this->insert('users', ['full_name' => 'B', 'email' => 'josé@example.com', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'customer', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00']);

        self::assertSame(1, $this->database->fetchOne('SELECT COUNT(*) AS total FROM users WHERE email = :email', ['email' => 'jose@example.com'])?->int('total'));
    }

    public function testTheDriverIsTheExpectedOne(): void
    {
        self::assertSame(TestDatabase::isExternal() ? Driver::MySql : Driver::Sqlite, $this->database->driver());
    }

    private static function migration(string $version): Migration
    {
        $migration = require TestDatabase::migrations() . '/' . $version . '.php';
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /**
     * @param array<string, int|string|null> $row
     */
    private function insert(string $table, array $row): void
    {
        $columns = array_keys($row);
        $this->database->execute(
            sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns))),
            $row,
        );
    }
}
