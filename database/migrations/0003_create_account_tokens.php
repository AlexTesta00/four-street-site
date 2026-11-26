<?php

declare(strict_types=1);

use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migration;

// I link monouso delle email (conferma dell'email, nuova password): solo l'impronta del token.
// Un link usato resta fino alla scadenza, per distinguere "già usato" da "inesistente".
return new class implements Migration {
    public function statements(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS account_tokens (
                        token_hash TEXT NOT NULL PRIMARY KEY,
                        user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                        purpose TEXT NOT NULL CHECK (purpose IN ('email_verification', 'password_reset')),
                        return_path TEXT NULL,
                        created_at TEXT NOT NULL,
                        expires_at TEXT NOT NULL,
                        used_at TEXT NULL
                    ) STRICT
                    SQL,
                'CREATE INDEX IF NOT EXISTS account_tokens_user_purpose_index ON account_tokens (user_id, purpose)',
                'CREATE INDEX IF NOT EXISTS account_tokens_expires_at_index ON account_tokens (expires_at)',
            ],
            Driver::MySql => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS account_tokens (
                        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        user_id BIGINT UNSIGNED NOT NULL,
                        purpose VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        return_path VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NULL,
                        created_at DATETIME NOT NULL,
                        expires_at DATETIME NOT NULL,
                        used_at DATETIME NULL,
                        PRIMARY KEY (token_hash),
                        KEY account_tokens_user_purpose_index (user_id, purpose),
                        KEY account_tokens_expires_at_index (expires_at),
                        CONSTRAINT account_tokens_purpose_check CHECK (purpose IN ('email_verification', 'password_reset')),
                        CONSTRAINT account_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
            ],
        };
    }
};
