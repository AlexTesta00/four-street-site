<?php

declare(strict_types=1);

use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migration;

// I contatori dei tentativi, una riga per impronta e finestra. "bucket" è l'impronta HMAC
// del limite e del soggetto (un indirizzo IP, un'email): il soggetto non si salva mai.
return new class implements Migration {
    public function statements(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS rate_limits (
                        bucket TEXT NOT NULL,
                        window_start TEXT NOT NULL,
                        hits INTEGER NOT NULL,
                        expires_at TEXT NOT NULL,
                        PRIMARY KEY (bucket, window_start)
                    ) STRICT
                    SQL,
                'CREATE INDEX IF NOT EXISTS rate_limits_expires_at_index ON rate_limits (expires_at)',
            ],
            Driver::MySql => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS rate_limits (
                        bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        window_start DATETIME NOT NULL,
                        hits INT UNSIGNED NOT NULL,
                        expires_at DATETIME NOT NULL,
                        PRIMARY KEY (bucket, window_start),
                        KEY rate_limits_expires_at_index (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
            ],
        };
    }
};
