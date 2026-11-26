<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Database;

use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\TemporaryDirectory;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Migrator::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Row::class)]
#[UsesClass(UtcDateTime::class)]
final class MigratorTest extends TestCase
{
    private TemporaryDirectory $migrations;

    private Database $database;

    protected function setUp(): void
    {
        $this->migrations = new TemporaryDirectory('4street-migrazioni');
        $this->database = new Database(DatabaseConfig::sqlite(':memory:'));
    }

    protected function tearDown(): void
    {
        $this->migrations->remove();
    }

    public function testAppliesThePendingMigrationsInOrderOnceOnly(): void
    {
        $this->migration('0002_add_notes.php', "'CREATE TABLE notes (id INTEGER PRIMARY KEY, text TEXT NOT NULL)'");
        $this->migration('0001_create_things.php', "'CREATE TABLE things (id INTEGER PRIMARY KEY)', 'CREATE INDEX things_id ON things (id)'");
        $this->migrations->file('LEGGIMI.txt', 'non è una migrazione');
        $migrator = $this->migrator();

        $reported = [];
        $applied = $migrator->migrate(static function (string $version) use (&$reported): void {
            $reported[] = $version;
        });

        self::assertSame(['0001_create_things', '0002_add_notes'], $applied);
        self::assertSame($applied, $reported);
        self::assertSame([], $migrator->migrate(), 'Rilanciare non rifà nulla.');
        self::assertSame(['0001_create_things' => true, '0002_add_notes' => true], $migrator->status());
        self::assertSame(
            ['0001_create_things' => '2026-11-25 07:00:00', '0002_add_notes' => '2026-11-25 07:00:00'],
            array_column(array_map(
                static fn(Row $row): array => ['version' => $row->string('version'), 'applied_at' => $row->string('applied_at')],
                $this->database->fetchAll('SELECT version, applied_at FROM schema_migrations ORDER BY version'),
            ), 'applied_at', 'version'),
        );
    }

    public function testANewMigrationIsAppliedLater(): void
    {
        $this->migration('0001_create_things.php', "'CREATE TABLE things (id INTEGER PRIMARY KEY)'");
        $migrator = $this->migrator();
        $migrator->migrate();

        $this->migration('0002_add_notes.php', "'CREATE TABLE notes (id INTEGER PRIMARY KEY)'");

        self::assertSame(['0001_create_things' => true, '0002_add_notes' => false], $migrator->status());
        self::assertSame(['0002_add_notes'], $migrator->migrate());
    }

    public function testStatusOnAnEmptyDatabaseDoesNotTouchIt(): void
    {
        $this->migration('0001_create_things.php', "'CREATE TABLE things (id INTEGER PRIMARY KEY)'");

        self::assertSame(['0001_create_things' => false], $this->migrator()->status());
        self::assertNull($this->database->fetchOne("SELECT name FROM sqlite_master WHERE name = 'schema_migrations'"));
    }

    public function testAFailedMigrationLeavesNothingBehindOnSqlite(): void
    {
        $this->migration('0001_broken.php', "'CREATE TABLE things (id INTEGER PRIMARY KEY)', 'CREATE TABLE things (id INTEGER PRIMARY KEY)'");

        try {
            $this->migrator()->migrate();
            self::fail('La migrazione doveva fallire.');
        } catch (PDOException) {
        }

        self::assertNull($this->database->fetchOne("SELECT name FROM sqlite_master WHERE name = 'things'"));
        self::assertSame(['0001_broken' => false], $this->migrator()->status());
    }

    public function testAMisnamedFileIsRefused(): void
    {
        $this->migration('1_create_things.php', "'CREATE TABLE things (id INTEGER PRIMARY KEY)'");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nome di migrazione non valido: 1_create_things.php. Usa NNNN_descrizione.php');

        $this->migrator()->migrate();
    }

    public function testAFileMustReturnAMigration(): void
    {
        $this->migrations->file('0001_wrong.php', "<?php\nreturn 'CREATE TABLE things (id INTEGER)';\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deve restituire un oggetto Migration');

        $this->migrator()->migrate();
    }

    public function testAMissingDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cartella delle migrazioni non trovata');

        (new Migrator($this->database, $this->migrations->path . '/non-esiste', FrozenClock::at('2026-11-25 08:00:00')))->migrate();
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->database, $this->migrations->path, FrozenClock::at('2026-11-25 08:00:00'));
    }

    /**
     * Una migrazione che restituisce le stesse istruzioni su ogni database.
     *
     * @param string $statements istruzioni SQL come elementi di un array PHP, già tra apici
     */
    private function migration(string $file, string $statements): void
    {
        $this->migrations->file($file, <<<PHP
            <?php

            declare(strict_types=1);

            use FourStreet\\Database\\Driver;
            use FourStreet\\Database\\Migration\\Migration;

            return new class implements Migration {
                public function statements(Driver \$driver): array
                {
                    return [{$statements}];
                }
            };
            PHP);
    }
}
