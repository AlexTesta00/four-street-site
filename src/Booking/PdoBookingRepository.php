<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use FourStreet\Database\Database;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use PDOException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Le prenotazioni nella tabella bookings (SQLite e MySQL/MariaDB, lo stesso SQL).
 *
 * place() lavora in una transazione che comincia bloccando la riga del cliente in users: le prenotazioni
 * dello stesso cliente passano così una alla volta (su MySQL un blocco di riga, su SQLite la scrittura
 * è comunque una alla volta), e i controlli "una al giorno" e "al massimo N future" non si possono
 * aggirare con due richieste insieme. Il posto invece non si blocca: si prova a scriverlo, e il vincolo
 * UNIQUE (fascia, posto) ne fa passare uno solo. Chi perde riprova da capo, in una transazione nuova
 * (con MySQL in REPEATABLE READ una lettura nella stessa transazione non vedrebbe il posto appena preso).
 * Ogni segnaposto compare una volta sola per istruzione: MySQL, senza emulazione, non ne accetta di ripetuti.
 */
final readonly class PdoBookingRepository implements BookingRepository
{
    private const string COLUMNS = 'id, user_id, slot_date, slot_time, starts_at, seat, created_at, cancelled_at, cancelled_by';

    /** Oltre a un tentativo per posto, un paio di riserva per gli stalli fra transazioni. */
    private const int EXTRA_ATTEMPTS = 2;

    /** Violazione di un vincolo (qui: il posto appena preso da un altro). */
    private const string INTEGRITY_VIOLATION = '23000';

    /** Stallo fra due transazioni (MySQL): si riprova da capo. */
    private const string SERIALIZATION_FAILURE = '40001';

    /**
     * MariaDB 11.6.2+ con innodb_snapshot_isolation (attivo di serie): "la riga è cambiata dopo la tua lettura".
     * È lo stesso caso di una gara persa: si riprova da capo, in una transazione nuova.
     */
    private const int RECORD_CHANGED_SINCE_READ = 1020;

    public function __construct(
        private Database $database,
        private SeatChooser $seats = new FirstFreeSeat(),
    ) {}

    public function place(int $userId, ScheduledSlot $slot, int $capacity, ?int $maxUpcoming, DateTimeImmutable $now): BookingResult
    {
        $attempts = $capacity + self::EXTRA_ATTEMPTS;
        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            try {
                $result = $this->database->transaction(fn(): ?BookingResult => $this->attempt($userId, $slot, $capacity, $maxUpcoming, $now));
            } catch (PDOException $error) {
                if (self::sqlState($error) === self::SERIALIZATION_FAILURE || ($error->errorInfo[1] ?? null) === self::RECORD_CHANGED_SINCE_READ) {
                    continue;
                }

                throw $error;
            }
            if ($result !== null) {
                return $result;
            }
        }

        throw new RuntimeException(sprintf('Nessun posto assegnato nella fascia %s dopo %d tentativi.', $slot->key(), $attempts));
    }

    public function find(int $id): ?Booking
    {
        $row = $this->database->fetchOne('SELECT ' . self::COLUMNS . ' FROM bookings WHERE id = :id', ['id' => $id]);

        return $row === null ? null : self::booking($row);
    }

    public function activeOn(int $userId, LocalDate $date): ?Booking
    {
        $row = $this->database->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM bookings WHERE user_id = :user_id AND slot_date = :slot_date AND active = 1',
            ['user_id' => $userId, 'slot_date' => $date->value],
        );

        return $row === null ? null : self::booking($row);
    }

    public function activeFrom(int $userId, LocalDate $from): array
    {
        return array_map(self::booking(...), $this->database->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bookings WHERE user_id = :user_id AND active = 1 AND slot_date >= :slot_date ORDER BY starts_at, id',
            ['user_id' => $userId, 'slot_date' => $from->value],
        ));
    }

    public function countUpcoming(int $userId, DateTimeImmutable $now): int
    {
        return $this->database->fetchOne(
            'SELECT COUNT(*) AS total FROM bookings WHERE user_id = :user_id AND active = 1 AND starts_at > :now',
            ['user_id' => $userId, 'now' => UtcDateTime::format($now)],
        )?->int('total') ?? 0;
    }

    public function occupancy(LocalDate $from, LocalDate $to): Occupancy
    {
        $seats = [];
        foreach ($this->database->fetchAll(
            'SELECT slot_date, slot_time, seat FROM bookings WHERE slot_date >= :from_date AND slot_date <= :to_date AND seat IS NOT NULL'
            . ' ORDER BY slot_date, slot_time, seat',
            ['from_date' => $from->value, 'to_date' => $to->value],
        ) as $row) {
            $seats[self::date($row)->value . ' ' . self::time($row)->value][] = $row->int('seat');
        }

        return new Occupancy($seats);
    }

    public function cancel(int $id, DateTimeImmutable $at, CancelledBy $by): bool
    {
        return $this->database->execute(
            'UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = :cancelled_at, cancelled_by = :cancelled_by WHERE id = :id AND active = 1',
            ['cancelled_at' => UtcDateTime::format($at), 'cancelled_by' => $by->value, 'id' => $id],
        ) > 0;
    }

    /**
     * Un tentativo, dentro la transazione: null se il posto scelto l'ha preso un altro nel frattempo.
     *
     * @param positive-int $capacity
     * @param positive-int|null $maxUpcoming
     */
    private function attempt(int $userId, ScheduledSlot $slot, int $capacity, ?int $maxUpcoming, DateTimeImmutable $now): ?BookingResult
    {
        // Prima di ogni lettura: da qui alla fine le altre prenotazioni di questo cliente aspettano.
        $this->database->execute('UPDATE users SET updated_at = updated_at WHERE id = :id', ['id' => $userId]);

        $existing = $this->activeOn($userId, $slot->date);
        if ($existing !== null) {
            return BookingResult::alreadyBooked($slot, $existing);
        }
        if ($maxUpcoming !== null && $this->countUpcoming($userId, $now) >= $maxUpcoming) {
            return BookingResult::tooManyUpcoming($slot);
        }

        $seat = $this->seats->choose($this->seatsOf($slot), $capacity);
        if ($seat === null) {
            return BookingResult::full($slot);
        }

        try {
            $this->database->execute(
                'INSERT INTO bookings (user_id, slot_date, slot_time, starts_at, seat, active, created_at)'
                . ' VALUES (:user_id, :slot_date, :slot_time, :starts_at, :seat, 1, :created_at)',
                [
                    'user_id' => $userId,
                    'slot_date' => $slot->date->value,
                    'slot_time' => $slot->time->value,
                    'starts_at' => UtcDateTime::format($slot->startsAt),
                    'seat' => $seat,
                    'created_at' => UtcDateTime::format($now),
                ],
            );
        } catch (PDOException $error) {
            if (self::sqlState($error) === self::INTEGRITY_VIOLATION) {
                return null;
            }

            throw $error;
        }

        $booking = $this->find($this->database->lastInsertId())
            ?? throw new UnexpectedValueException('La prenotazione appena scritta non si trova.');

        return BookingResult::booked($slot, $booking);
    }

    /**
     * @return list<int>
     */
    private function seatsOf(ScheduledSlot $slot): array
    {
        return array_map(
            static fn(Row $row): int => $row->int('seat'),
            $this->database->fetchAll(
                'SELECT seat FROM bookings WHERE slot_date = :slot_date AND slot_time = :slot_time AND seat IS NOT NULL ORDER BY seat',
                ['slot_date' => $slot->date->value, 'slot_time' => $slot->time->value],
            ),
        );
    }

    private static function booking(Row $row): Booking
    {
        $cancelledBy = $row->nullableString('cancelled_by');

        return new Booking(
            id: $row->int('id'),
            userId: $row->int('user_id'),
            date: self::date($row),
            time: self::time($row),
            startsAt: $row->dateTime('starts_at'),
            seat: $row->nullableInt('seat'),
            createdAt: $row->dateTime('created_at'),
            cancelledAt: $row->nullableDateTime('cancelled_at'),
            cancelledBy: $cancelledBy === null ? null : (CancelledBy::tryFrom($cancelledBy)
                ?? throw new UnexpectedValueException(sprintf('Autore della disdetta sconosciuto nel database: "%s".', $cancelledBy))),
        );
    }

    private static function date(Row $row): LocalDate
    {
        $value = $row->string('slot_date');

        return LocalDate::tryFrom($value) ?? throw new UnexpectedValueException(sprintf('Giorno di fascia non valido nel database: "%s".', $value));
    }

    private static function time(Row $row): SlotTime
    {
        $value = $row->string('slot_time');

        return SlotTime::tryFrom($value) ?? throw new UnexpectedValueException(sprintf('Orario di fascia non valido nel database: "%s".', $value));
    }

    private static function sqlState(PDOException $error): ?string
    {
        $state = $error->errorInfo[0] ?? null;

        return is_string($state) ? $state : null;
    }
}
