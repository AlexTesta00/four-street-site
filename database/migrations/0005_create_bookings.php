<?php

declare(strict_types=1);

use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migration;

// Le prenotazioni delle fasce. La fascia è il giorno e l'orario d'inizio, ora locale del sito
// (slot_date AAAA-MM-GG, slot_time HH:MM); starts_at è lo stesso inizio in UTC, per i confronti con adesso.
// La capienza la garantisce il database: seat è il posto (da 1 al numero di posti) e UNIQUE (fascia, posto)
// fa passare una sola prenotazione per posto. Allo stesso modo active vale 1 e UNIQUE (cliente, giorno, active)
// lascia al cliente una sola prenotazione attiva al giorno. Disdetta, la prenotazione libera il posto
// (seat e active a NULL: i NULL non si scontrano nei vincoli UNIQUE, né su SQLite né su MySQL) e resta
// come storico, con quando e chi l'ha disdetta (customer oppure admin). Date e ore tecniche in UTC.
// Il CHECK dello stato confronta i NULL con IS (NOT) NULL: un CHECK fallisce solo se è falso, e con
// "active = 1" una riga a metà (un posto senza active) darebbe "sconosciuto" e passerebbe.
return new class implements Migration {
    public function statements(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS bookings (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                        slot_date TEXT NOT NULL,
                        slot_time TEXT NOT NULL,
                        starts_at TEXT NOT NULL,
                        seat INTEGER NULL CHECK (seat >= 1),
                        active INTEGER NULL CHECK (active = 1),
                        created_at TEXT NOT NULL,
                        cancelled_at TEXT NULL,
                        cancelled_by TEXT NULL CHECK (cancelled_by IN ('customer', 'admin')),
                        CHECK (
                            (active IS NOT NULL AND seat IS NOT NULL AND cancelled_at IS NULL AND cancelled_by IS NULL)
                            OR (active IS NULL AND seat IS NULL AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL)
                        ),
                        UNIQUE (slot_date, slot_time, seat),
                        UNIQUE (user_id, slot_date, active)
                    ) STRICT
                    SQL,
            ],
            Driver::MySql => [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS bookings (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        user_id BIGINT UNSIGNED NOT NULL,
                        slot_date DATE NOT NULL,
                        slot_time CHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                        starts_at DATETIME NOT NULL,
                        seat SMALLINT UNSIGNED NULL,
                        active TINYINT UNSIGNED NULL,
                        created_at DATETIME NOT NULL,
                        cancelled_at DATETIME NULL,
                        cancelled_by VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
                        PRIMARY KEY (id),
                        UNIQUE KEY bookings_seat_unique (slot_date, slot_time, seat),
                        UNIQUE KEY bookings_one_per_day_unique (user_id, slot_date, active),
                        CONSTRAINT bookings_seat_check CHECK (seat >= 1),
                        CONSTRAINT bookings_active_check CHECK (active = 1),
                        CONSTRAINT bookings_cancelled_by_check CHECK (cancelled_by IN ('customer', 'admin')),
                        CONSTRAINT bookings_state_check CHECK (
                            (active IS NOT NULL AND seat IS NOT NULL AND cancelled_at IS NULL AND cancelled_by IS NULL)
                            OR (active IS NULL AND seat IS NULL AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL)
                        ),
                        CONSTRAINT bookings_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL,
            ],
        };
    }
};
