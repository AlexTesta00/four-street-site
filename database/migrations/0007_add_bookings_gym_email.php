<?php

declare(strict_types=1);

use FourStreet\Database\Driver;
use FourStreet\Database\Migration\ColumnMigration;

// Com'è andata l'email della palestra quando a disdire una prenotazione è lei (il titolare) e il cliente ha l'account:
// gym_email. La disdetta la scrive "pending" (in attesa) nella stessa istruzione, prima di ogni invio; chi la manda prima
// la prende ("sending", in invio: da in attesa o non partita, un passo solo che il database fa vincere a una richiesta
// sola), poi la segna "sent" (partita) o "failed" (non partita). Una riga rimasta "sending" (il processo interrotto
// proprio durante l'invio) non si rimanda: non si sa se l'email è partita.
// NULL per tutto il resto: le prenotazioni attive, quelle disdette dal cliente, chi non ha l'account (nessuna email,
// lo avvisa il titolare: si capisce da user_id NULL) e le disdette della palestra di prima di questa migrazione, che
// restano senza esito.
//
// Una colonna nuova in una tabella che c'è già (ColumnMigration): se la colonna c'è, la migrazione è fatta. Per questo
// su MySQL l'istruzione è una sola, atomica, con la colonna e i suoi due CHECK. Come in 0005 i CHECK confrontano i NULL
// con IS (NOT) NULL (un CHECK fallisce solo se è falso), e il secondo usa user_id, che ha ON DELETE CASCADE: MariaDB lo
// accetta, come il CHECK della persona. Su SQLite i CHECK di una colonna aggiunta leggono anche le altre colonne della
// riga, e si provano subito sulle righe che ci sono (qui tutte NULL).
return new class implements ColumnMigration {
    public function table(): string
    {
        return 'bookings';
    }

    public function column(): string
    {
        return 'gym_email';
    }

    public function statements(Driver $driver): array
    {
        return match ($driver) {
            Driver::Sqlite => [
                <<<'SQL'
                    ALTER TABLE bookings ADD COLUMN gym_email TEXT NULL
                        CHECK (gym_email IN ('pending', 'sending', 'sent', 'failed'))
                        CHECK (gym_email IS NULL OR (cancelled_by IS NOT NULL AND cancelled_by = 'admin' AND user_id IS NOT NULL))
                    SQL,
            ],
            Driver::MySql => [
                <<<'SQL'
                    ALTER TABLE bookings
                        ADD COLUMN gym_email VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
                        ADD CONSTRAINT bookings_gym_email_check CHECK (gym_email IN ('pending', 'sending', 'sent', 'failed')),
                        ADD CONSTRAINT bookings_gym_email_owner_check CHECK (
                            gym_email IS NULL
                            OR (cancelled_by IS NOT NULL AND cancelled_by = 'admin' AND user_id IS NOT NULL)
                        )
                    SQL,
            ],
        };
    }
};
