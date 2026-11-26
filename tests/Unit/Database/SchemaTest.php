<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Database;

use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Driver;
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
        // Una migrazione interrotta a metà (su MySQL lo schema non è transazionale) si rilancia così com'è.
        foreach (self::MIGRATIONS as $version) {
            $migration = require TestDatabase::migrations() . '/' . $version . '.php';
            self::assertInstanceOf(Migration::class, $migration);
            foreach ($migration->statements($this->database->driver()) as $statement) {
                $this->database->execute($statement);
            }
        }

        self::assertSame(0, $this->database->fetchOne('SELECT COUNT(*) AS total FROM users')?->int('total'));
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
    }

    /**
     * @param array<string, int|string|null> $row
     */
    #[DataProvider('rejectedRows')]
    public function testTheSchemaRefusesInvalidRows(string $table, array $row): void
    {
        $this->insert('users', ['full_name' => 'A', 'email' => 'a@example.com', 'password_hash' => 'h', 'privacy_accepted_at' => '2026-11-25 07:00:00', 'role' => 'customer', 'created_at' => '2026-11-25 07:00:00', 'updated_at' => '2026-11-25 07:00:00']);
        if ($table === 'account_tokens') {
            $row['user_id'] = $this->database->lastInsertId();
        }

        $this->expectException(PDOException::class);

        $this->insert($table, $row);
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
