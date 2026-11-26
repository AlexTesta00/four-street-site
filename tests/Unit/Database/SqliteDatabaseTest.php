<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Database;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\InvalidSiteConfig;
use FourStreet\Config\Paths;
use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Driver;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * Ciò che vale solo per SQLite (il file, la sua cartella) e le letture tipizzate delle righe.
 */
#[CoversClass(Database::class)]
#[CoversClass(DatabaseConfig::class)]
#[CoversClass(Row::class)]
#[CoversClass(UtcDateTime::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(InvalidSiteConfig::class)]
#[UsesClass(Paths::class)]
final class SqliteDatabaseTest extends TestCase
{
    public function testAFileDatabaseCreatesItsDirectory(): void
    {
        $temporary = new TemporaryDirectory();
        try {
            $database = new Database(DatabaseConfig::sqlite($temporary->path . '/var/dati/4street.sqlite'));
            $database->execute('CREATE TABLE prova (id INTEGER PRIMARY KEY)');

            self::assertFileExists($temporary->path . '/var/dati/4street.sqlite');
        } finally {
            unset($database);
            gc_collect_cycles();
            $temporary->remove();
        }
    }

    public function testADirectoryThatCannotBeCreatedIsAClearError(): void
    {
        $database = new Database(DatabaseConfig::sqlite(__FILE__ . '/dentro-un-file/4street.sqlite'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Impossibile creare la cartella del database');

        $database->execute('SELECT 1');
    }

    public function testTheDriverComesFromTheDsn(): void
    {
        self::assertSame(Driver::MySql, (new DatabaseConfig('mysql:host=127.0.0.1;dbname=a', 'u', 'p'))->driver);
        self::assertSame(Driver::Sqlite, (new DatabaseConfig('SQLITE::memory:'))->driver);
        self::assertNull((new DatabaseConfig('mysql:host=127.0.0.1;dbname=a'))->sqliteFile());
    }

    public function testAnUnsupportedDsn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN non supportato: "pgsql:host=localhost"');

        new DatabaseConfig('pgsql:host=localhost');
    }

    public function testRowsRefuseTheWrongTypes(): void
    {
        $row = new Row(['id' => '42', 'name' => 7, 'when' => '2026-02-30 10:00:00', 'big' => '9999999999999999999']);

        self::assertSame(42, $row->int('id'), 'Un intero come testo è un intero.');
        foreach ([
            static fn(): mixed => $row->string('name'),
            static fn(): mixed => $row->int('big'),
            static fn(): mixed => $row->dateTime('when'),
            static fn(): mixed => $row->int('missing'),
        ] as $read) {
            try {
                $read();
                self::fail('La lettura doveva fallire.');
            } catch (UnexpectedValueException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testUtcDatesRoundTrip(): void
    {
        $rome = new DateTimeImmutable('2026-12-06 15:00:00', new DateTimeZone('Europe/Rome'));

        self::assertSame('2026-12-06 14:00:00', UtcDateTime::format($rome));
        self::assertSame($rome->getTimestamp(), UtcDateTime::parse('2026-12-06 14:00:00')->getTimestamp());
        self::assertSame('UTC', UtcDateTime::parse('2026-12-06 14:00:00')->getTimezone()->getName());
    }
}
