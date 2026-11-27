<?php

declare(strict_types=1);

use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migration;

// L'orario e le regole che il titolare cambia dal pannello. config/site.php resta il valore iniziale e di riserva:
// finché il titolare non salva nulla vale il file.
//
// booking_settings: le regole salvate dal titolare (online, days_ahead, booking_closes_minutes_before,
// cancel_closes_minutes_before, max_upcoming), una riga per chiave: una chiave che manca vale com'è nel file.
// La riga "schedule" = "database" segna che l'orario sta nel database: la scrive la prima modifica dell'orario,
// insieme alla copia della settimana tipo e dei closed_dates del file. Da lì in poi l'orario del file non conta più.
//
// schedule_weekly_slots: la settimana tipo, un orario d'inizio per riga, con il periodo in cui vale
// (valid_from compreso, valid_until escluso; NULL: da sempre, per sempre). Una modifica vale da domani: le righe
// di prima si chiudono, quelle nuove cominciano, e i giorni passati (e oggi) restano com'erano.
//
// schedule_days: le eccezioni, un giorno con un orario suo al posto della settimana tipo; chiuso (closed = 1,
// senza fasce) oppure con le fasce di schedule_day_slots. La nota è a uso interno del titolare, mai per i clienti.
// updated_by e updated_at dicono chi e quando; cancellato l'account, la modifica resta senza autore.
//
// I CHECK non toccano updated_by: MySQL e MariaDB rifiutano un CHECK sulle colonne che ON DELETE SET NULL riscrive.
// Gli orari sono "HH:MM" come in bookings.slot_time; giorni AAAA-MM-GG, istanti in UTC.
return new class implements Migration {
    public function statements(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS booking_settings (
                        name TEXT NOT NULL PRIMARY KEY CHECK (length(name) BETWEEN 1 AND 64),
                        value TEXT NOT NULL CHECK (length(value) BETWEEN 1 AND 255),
                        updated_by INTEGER NULL REFERENCES users (id) ON DELETE SET NULL,
                        updated_at TEXT NOT NULL
                    ) STRICT
                    SQL,
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS schedule_weekly_slots (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        weekday INTEGER NOT NULL CHECK (weekday BETWEEN 1 AND 7),
                        slot_time TEXT NOT NULL CHECK (length(slot_time) = 5),
                        valid_from TEXT NULL,
                        valid_until TEXT NULL,
                        CHECK (valid_from IS NULL OR valid_until IS NULL OR valid_from < valid_until)
                    ) STRICT
                    SQL,
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS schedule_days (
                        slot_date TEXT NOT NULL PRIMARY KEY,
                        closed INTEGER NOT NULL CHECK (closed IN (0, 1)),
                        note TEXT NULL CHECK (length(note) BETWEEN 1 AND 100),
                        updated_by INTEGER NULL REFERENCES users (id) ON DELETE SET NULL,
                        updated_at TEXT NOT NULL
                    ) STRICT
                    SQL,
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS schedule_day_slots (
                        slot_date TEXT NOT NULL REFERENCES schedule_days (slot_date) ON DELETE CASCADE,
                        slot_time TEXT NOT NULL CHECK (length(slot_time) = 5),
                        PRIMARY KEY (slot_date, slot_time)
                    ) STRICT
                    SQL,
            ],
            Driver::MySql => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS booking_settings (
                        name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        value VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        updated_by BIGINT UNSIGNED NULL,
                        updated_at DATETIME NOT NULL,
                        PRIMARY KEY (name),
                        CONSTRAINT booking_settings_value_check CHECK (CHAR_LENGTH(value) BETWEEN 1 AND 255),
                        CONSTRAINT booking_settings_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS schedule_weekly_slots (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        weekday TINYINT UNSIGNED NOT NULL,
                        slot_time CHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        valid_from DATE NULL,
                        valid_until DATE NULL,
                        PRIMARY KEY (id),
                        KEY schedule_weekly_slots_weekday_index (weekday, slot_time),
                        CONSTRAINT schedule_weekly_slots_weekday_check CHECK (weekday BETWEEN 1 AND 7),
                        CONSTRAINT schedule_weekly_slots_time_check CHECK (CHAR_LENGTH(slot_time) = 5),
                        CONSTRAINT schedule_weekly_slots_validity_check CHECK (valid_from IS NULL OR valid_until IS NULL OR valid_from < valid_until)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS schedule_days (
                        slot_date DATE NOT NULL,
                        closed TINYINT UNSIGNED NOT NULL,
                        note VARCHAR(100) NULL,
                        updated_by BIGINT UNSIGNED NULL,
                        updated_at DATETIME NOT NULL,
                        PRIMARY KEY (slot_date),
                        CONSTRAINT schedule_days_closed_check CHECK (closed IN (0, 1)),
                        CONSTRAINT schedule_days_note_check CHECK (CHAR_LENGTH(note) BETWEEN 1 AND 100),
                        CONSTRAINT schedule_days_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS schedule_day_slots (
                        slot_date DATE NOT NULL,
                        slot_time CHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        PRIMARY KEY (slot_date, slot_time),
                        CONSTRAINT schedule_day_slots_time_check CHECK (CHAR_LENGTH(slot_time) = 5),
                        CONSTRAINT schedule_day_slots_slot_date_foreign FOREIGN KEY (slot_date) REFERENCES schedule_days (slot_date) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
            ],
        };
    }
};
