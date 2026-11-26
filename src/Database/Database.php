<?php

declare(strict_types=1);

namespace FourStreet\Database;

use Closure;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Il database, dietro PDO. La connessione è pigra: si apre alla prima query, quindi le pagine che non
 * leggono dati (la home, i link, il calendario) non la aprono mai e funzionano anche a database spento.
 * Errori come eccezioni, prepared statement veri (niente emulazione), chiavi esterne attive anche su SQLite,
 * utf8mb4 e fuso UTC su MySQL.
 */
final class Database
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    public function driver(): Driver
    {
        return $this->config->driver;
    }

    /** La connessione è già stata aperta da una query. */
    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Esegue un'istruzione (INSERT, UPDATE, DELETE, DDL).
     *
     * @param array<string, int|string|null> $parameters segnaposto con nome (":email") => valore
     *
     * @return int righe toccate
     */
    public function execute(string $sql, array $parameters = []): int
    {
        return $this->run($sql, $parameters)->rowCount();
    }

    /**
     * La prima riga del risultato, oppure null se non ce ne sono.
     *
     * @param array<string, int|string|null> $parameters
     */
    public function fetchOne(string $sql, array $parameters = []): ?Row
    {
        $statement = $this->run($sql, $parameters);
        $row = $statement->fetch();
        $statement->closeCursor();

        return $row === false ? null : self::row($row);
    }

    /**
     * @param array<string, int|string|null> $parameters
     *
     * @return list<Row>
     */
    public function fetchAll(string $sql, array $parameters = []): array
    {
        return array_map(self::row(...), array_values($this->run($sql, $parameters)->fetchAll()));
    }

    /** L'id dell'ultima riga inserita in questa connessione (chiave AUTO_INCREMENT o INTEGER PRIMARY KEY). */
    public function lastInsertId(): int
    {
        $id = $this->connection()->lastInsertId();
        if ($id === false || preg_match('/\A\d+\z/', $id) !== 1) {
            throw new RuntimeException('Il database non ha restituito l\'id della riga inserita.');
        }

        return (int) $id;
    }

    /**
     * Esegue $work in una transazione: commit se finisce, rollback se lancia un'eccezione.
     * Dentro una transazione già aperta $work entra in quella: niente transazioni annidate.
     * Non per lo schema su MySQL, dove CREATE e ALTER chiudono da soli la transazione.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function transaction(Closure $work): mixed
    {
        $connection = $this->connection();
        if ($connection->inTransaction()) {
            return $work();
        }

        $connection->beginTransaction();
        try {
            $result = $work();
        } catch (Throwable $error) {
            $connection->rollBack();

            throw $error;
        }
        $connection->commit();

        return $result;
    }

    /**
     * @param array<string, int|string|null> $parameters
     */
    private function run(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connection()->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Il database non ha preparato l\'istruzione.');
        }
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . ltrim($name, ':'), $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();

        return $statement;
    }

    private function connection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $file = $this->config->sqliteFile();
        if ($file !== null) {
            self::ensureDirectory(dirname($file));
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        if ($this->config->driver === Driver::MySql) {
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        $connection = new PDO($this->config->dsn, $this->config->username, $this->config->password, $options);
        foreach (self::sessionSetup($this->config->driver) as $sql) {
            $connection->exec($sql);
        }

        return $this->connection = $connection;
    }

    /**
     * Impostazioni di ogni connessione, uguali su ogni server.
     *
     * @return list<string>
     */
    private static function sessionSetup(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                'PRAGMA foreign_keys = ON',
                // Con due richieste in scrittura insieme, la seconda aspetta invece di fallire subito.
                'PRAGMA busy_timeout = 5000',
            ],
            Driver::MySql => [
                'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                "SET time_zone = '+00:00'",
                "SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
            ],
        };
    }

    private static function row(mixed $values): Row
    {
        if (!is_array($values)) {
            throw new RuntimeException('Il database ha restituito una riga che non è un elenco di colonne.');
        }
        $columns = [];
        foreach ($values as $column => $value) {
            $columns[(string) $column] = $value;
        }

        return new Row($columns);
    }

    private static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Impossibile creare la cartella del database: %s', $directory));
        }
    }
}
