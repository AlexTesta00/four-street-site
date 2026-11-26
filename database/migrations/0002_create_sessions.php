<?php

declare(strict_types=1);

use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migration;

// Le sessioni aperte: solo l'impronta (SHA-256) del token del cookie. Si chiudono con l'utente.
return new class implements Migration {
    public function statements(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS sessions (
                        token_hash TEXT NOT NULL PRIMARY KEY,
                        user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                        created_at TEXT NOT NULL,
                        last_seen_at TEXT NOT NULL,
                        expires_at TEXT NOT NULL
                    ) STRICT
                    SQL,
                'CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id)',
                'CREATE INDEX IF NOT EXISTS sessions_expires_at_index ON sessions (expires_at)',
            ],
            Driver::MySql => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS sessions (
                        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        user_id BIGINT UNSIGNED NOT NULL,
                        created_at DATETIME NOT NULL,
                        last_seen_at DATETIME NOT NULL,
                        expires_at DATETIME NOT NULL,
                        PRIMARY KEY (token_hash),
                        KEY sessions_user_id_index (user_id),
                        KEY sessions_expires_at_index (expires_at),
                        CONSTRAINT sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
            ],
        };
    }
};
