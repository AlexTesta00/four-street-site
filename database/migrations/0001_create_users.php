<?php

declare(strict_types=1);

use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migration;

// Gli account dell'area clienti. L'email è normalizzata (minuscole) e unica; date e ore in UTC.
// signup_browser_hash: impronta del cookie del browser che ha scelto la password di una registrazione
// non ancora confermata; solo lì il link di conferma basta da solo (altrove chiede la password).
// Si azzera alla conferma.
return new class implements Migration {
    public function statements(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        full_name TEXT NOT NULL,
                        email TEXT NOT NULL UNIQUE,
                        password_hash TEXT NOT NULL,
                        email_verified_at TEXT NULL,
                        privacy_accepted_at TEXT NOT NULL,
                        role TEXT NOT NULL DEFAULT 'customer' CHECK (role IN ('customer', 'admin')),
                        signup_browser_hash TEXT NULL,
                        created_at TEXT NOT NULL,
                        updated_at TEXT NOT NULL
                    ) STRICT
                    SQL,
            ],
            Driver::MySql => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS users (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        full_name VARCHAR(100) NOT NULL,
                        email VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                        password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        email_verified_at DATETIME NULL,
                        privacy_accepted_at DATETIME NOT NULL,
                        role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'customer',
                        signup_browser_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL,
                        PRIMARY KEY (id),
                        UNIQUE KEY users_email_unique (email),
                        CONSTRAINT users_role_check CHECK (role IN ('customer', 'admin'))
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
            ],
        };
    }
};
