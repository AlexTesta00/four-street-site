<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Clock\Clock;
use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migrator;

/**
 * Il database dei test, migrato e vuoto. Di solito SQLite in memoria, nuovo per ogni test.
 * Con FOURSTREET_TEST_DATABASE_DSN (più utente e password) è un database vero, per esempio il MariaDB
 * della CI: le stesse migrazioni, e le tabelle svuotate prima di ogni test.
 *
 *   FOURSTREET_TEST_DATABASE_DSN="mysql:host=127.0.0.1;port=3306;dbname=fourstreet_test" \
 *   FOURSTREET_TEST_DATABASE_USERNAME=fourstreet FOURSTREET_TEST_DATABASE_PASSWORD=… vendor/bin/phpunit --group database
 */
final class TestDatabase
{
    public const string DSN_VARIABLE = 'FOURSTREET_TEST_DATABASE_DSN';
    public const string USERNAME_VARIABLE = 'FOURSTREET_TEST_DATABASE_USERNAME';
    public const string PASSWORD_VARIABLE = 'FOURSTREET_TEST_DATABASE_PASSWORD';

    /** Le tabelle da svuotare, prima quelle che dipendono dalle altre. */
    private const array TABLES = ['bookings', 'rate_limits', 'account_tokens', 'sessions', 'users'];

    public static function create(?Clock $clock = null): Database
    {
        $dsn = self::variable(self::DSN_VARIABLE);
        $database = new Database($dsn === null
            ? DatabaseConfig::sqlite(':memory:')
            : new DatabaseConfig($dsn, self::variable(self::USERNAME_VARIABLE), self::variable(self::PASSWORD_VARIABLE)));

        (new Migrator($database, self::migrations(), $clock ?? FrozenClock::at('2026-11-25 08:00:00')))->migrate();
        if ($database->driver() === Driver::MySql) {
            foreach (self::TABLES as $table) {
                $database->execute('DELETE FROM ' . $table);
            }
        }

        return $database;
    }

    /** I test girano su un database vero indicato dalle variabili d'ambiente, non su SQLite in memoria. */
    public static function isExternal(): bool
    {
        return self::variable(self::DSN_VARIABLE) !== null;
    }

    public static function migrations(): string
    {
        return SiteFixture::rootDirectory() . '/database/migrations';
    }

    private static function variable(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
