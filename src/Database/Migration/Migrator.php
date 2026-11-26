<?php

declare(strict_types=1);

namespace FourStreet\Database\Migration;

use Closure;
use FourStreet\Clock\Clock;
use FourStreet\Database\Database;
use FourStreet\Database\Driver;
use FourStreet\Database\UtcDateTime;
use RuntimeException;

/**
 * Applica le migrazioni di database/migrations/ che il database non ha ancora, in ordine di nome.
 * La tabella schema_migrations tiene il registro: rilanciare migrate non rifà nulla.
 * Su SQLite ogni migrazione è una transazione (entra tutta o niente); su MySQL lo schema non è
 * transazionale, e per questo ogni istruzione di una migrazione è scritta per poter girare di nuovo.
 */
final readonly class Migrator
{
    public const string TABLE = 'schema_migrations';

    /** "0001_create_users.php": quattro cifre, un trattino basso, la descrizione in minuscolo. */
    private const string FILE_PATTERN = '/\A(\d{4}_[a-z0-9_]+)\.php\z/';

    /**
     * @param string $directory cartella dei file di migrazione
     */
    public function __construct(
        private Database $database,
        private string $directory,
        private Clock $clock,
    ) {}

    /**
     * Applica le migrazioni che mancano.
     *
     * @param (Closure(string): void)|null $applied chiamata dopo ogni migrazione applicata, con il suo nome
     *
     * @return list<string> i nomi delle migrazioni applicate adesso, in ordine
     */
    public function migrate(?Closure $applied = null): array
    {
        $this->createRegistry();
        $done = $this->appliedVersions();

        $versions = [];
        foreach ($this->migrations() as $version => $migration) {
            if (isset($done[$version])) {
                continue;
            }
            $this->apply($version, $migration);
            $versions[] = $version;
            if ($applied !== null) {
                $applied($version);
            }
        }

        return $versions;
    }

    /**
     * Tutte le migrazioni, in ordine, con lo stato.
     *
     * @return array<string, bool> nome => già applicata
     */
    public function status(): array
    {
        $done = $this->registryExists() ? $this->appliedVersions() : [];

        $status = [];
        foreach (array_keys($this->migrations()) as $version) {
            $status[$version] = isset($done[$version]);
        }

        return $status;
    }

    private function apply(string $version, Migration $migration): void
    {
        $apply = function () use ($version, $migration): void {
            foreach ($migration->statements($this->database->driver()) as $sql) {
                $this->database->execute($sql);
            }
            $this->database->execute(
                'INSERT INTO ' . self::TABLE . ' (version, applied_at) VALUES (:version, :applied_at)',
                ['version' => $version, 'applied_at' => UtcDateTime::format($this->clock->now())],
            );
        };

        if ($this->database->driver() === Driver::Sqlite) {
            $this->database->transaction($apply);
        } else {
            $apply();
        }
    }

    /**
     * @return array<string, Migration> nome => migrazione, in ordine di nome
     */
    private function migrations(): array
    {
        $entries = is_dir($this->directory) ? scandir($this->directory) : false;
        if ($entries === false) {
            throw new RuntimeException(sprintf('Cartella delle migrazioni non trovata: %s', $this->directory));
        }

        $migrations = [];
        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.php')) {
                continue;
            }
            if (preg_match(self::FILE_PATTERN, $entry, $matches) !== 1) {
                throw new RuntimeException(sprintf('Nome di migrazione non valido: %s. Usa NNNN_descrizione.php, per esempio 0005_create_bookings.php.', $entry));
            }
            $migrations[$matches[1]] = self::load($this->directory . '/' . $entry);
        }
        ksort($migrations, SORT_STRING);

        return $migrations;
    }

    private static function load(string $file): Migration
    {
        // Funzione statica: il file vede solo se stesso, non il Migrator.
        $migration = (static fn(string $__file): mixed => require $__file)($file);
        if (!$migration instanceof Migration) {
            throw new RuntimeException(sprintf('Il file %s deve restituire un oggetto Migration.', $file));
        }

        return $migration;
    }

    /**
     * @return array<string, true>
     */
    private function appliedVersions(): array
    {
        $versions = [];
        foreach ($this->database->fetchAll('SELECT version FROM ' . self::TABLE) as $row) {
            $versions[$row->string('version')] = true;
        }

        return $versions;
    }

    private function createRegistry(): void
    {
        $this->database->execute(match ($this->database->driver()) {
            Driver::Sqlite => 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                version TEXT NOT NULL PRIMARY KEY,
                applied_at TEXT NOT NULL
            ) STRICT',
            Driver::MySql => 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                version VARCHAR(191) NOT NULL,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        });
    }

    private function registryExists(): bool
    {
        return $this->database->fetchOne(match ($this->database->driver()) {
            Driver::Sqlite => "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name",
            Driver::MySql => 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name',
        }, ['name' => self::TABLE]) !== null;
    }
}
