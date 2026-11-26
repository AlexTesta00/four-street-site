<?php

declare(strict_types=1);

namespace FourStreet\Database;

use FourStreet\Config\ConfigValues;
use FourStreet\Config\Paths;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Come raggiungere il database: DSN di PDO, utente e password.
 * SQLite: "sqlite:/percorso/4street.sqlite" (un percorso relativo parte dalla radice dell'applicazione)
 * oppure "sqlite::memory:". MySQL e MariaDB: "mysql:host=127.0.0.1;port=3306;dbname=…".
 */
final readonly class DatabaseConfig
{
    private const array KEYS = ['dsn', 'username', 'password'];

    public Driver $driver;

    public function __construct(
        public string $dsn,
        public ?string $username = null,
        #[SensitiveParameter]
        public ?string $password = null,
    ) {
        $this->driver = self::driverOf($dsn)
            ?? throw new InvalidArgumentException(sprintf('DSN non supportato: "%s". Usa "mysql:…" oppure "sqlite:…".', $dsn));
    }

    /**
     * Sezione "database" di config/local.php.
     *
     * @param string $appRoot radice dell'applicazione, per i percorsi SQLite relativi
     */
    public static function fromConfig(ConfigValues $database, string $appRoot): self
    {
        $database->assertKnownKeys(self::KEYS);
        $dsn = $database->text('dsn');
        $driver = self::driverOf($dsn);
        if ($driver === null) {
            throw $database->invalid('dsn', 'deve iniziare con "mysql:" (MySQL o MariaDB) oppure con "sqlite:"', $dsn);
        }
        if ($driver === Driver::MySql && preg_match('/(?:\A|[:;])dbname=[^;]+/', $dsn) !== 1) {
            throw $database->invalid('dsn', 'deve indicare il database con dbname=…', $dsn);
        }

        return new self(
            $driver === Driver::Sqlite ? self::absoluteSqliteDsn($dsn, $appRoot) : $dsn,
            $database->optionalText('username'),
            $database->optionalSecret('password'),
        );
    }

    /** Database SQLite in un file: si crea al primo accesso, con la sua cartella. */
    public static function sqlite(string $file): self
    {
        return new self('sqlite:' . $file);
    }

    /** Il file del database SQLite, oppure null (database in memoria, oppure MySQL). */
    public function sqliteFile(): ?string
    {
        if ($this->driver !== Driver::Sqlite) {
            return null;
        }
        $file = substr($this->dsn, strlen('sqlite:'));

        return $file === '' || $file === ':memory:' ? null : $file;
    }

    private static function driverOf(string $dsn): ?Driver
    {
        return str_contains($dsn, ':') ? Driver::tryFrom(strtolower(explode(':', $dsn, 2)[0])) : null;
    }

    private static function absoluteSqliteDsn(string $dsn, string $appRoot): string
    {
        $file = substr($dsn, strlen('sqlite:'));

        return $file === '' || $file === ':memory:' ? $dsn : 'sqlite:' . Paths::resolve($file, $appRoot);
    }
}
