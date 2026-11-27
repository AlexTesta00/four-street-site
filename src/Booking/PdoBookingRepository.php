<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use Closure;
use DateTimeImmutable;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
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
 * aggirare con due richieste insieme. placeGuest() fa lo stesso con la riga del titolare che la inserisce:
 * lo stesso modulo inviato due volte trova la prima prenotazione. Il posto invece non si blocca: si prova
 * a scriverlo, e il vincolo UNIQUE (fascia, posto) ne fa passare uno solo. Chi perde riprova da capo,
 * in una transazione nuova (con MySQL in REPEATABLE READ una lettura nella stessa transazione non vedrebbe
 * il posto appena preso).
 * Ogni segnaposto compare una volta sola per istruzione: MySQL, senza emulazione, non ne accetta di ripetuti.
 */
final readonly class PdoBookingRepository implements BookingRepository
{
    /** Le colonne di una prenotazione, come le legge booking(). */
    private const array COLUMNS = ['id', 'user_id', 'guest_name', 'slot_date', 'slot_time', 'starts_at', 'seat', 'created_at', 'created_by', 'cancelled_at', 'cancelled_by'];

    /** Le prenotazioni con la loro persona: per un cliente registrato nome ed email dell'account. */
    private const string WITH_PERSON = ', u.full_name AS full_name, u.email AS email FROM bookings b LEFT JOIN users u ON u.id = b.user_id';

    /** Oltre a un tentativo per posto, un paio di riserva per gli stalli fra transazioni. */
    private const int EXTRA_ATTEMPTS = 2;

    /** Violazione di un vincolo (qui: il posto appena preso da un altro, o la stessa persona appena inserita). */
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

    public function place(int $userId, ScheduledSlot $slot, int $capacity, ?int $maxUpcoming, DateTimeImmutable $now, CreatedBy $createdBy): BookingResult
    {
        return $this->retrying($slot, $capacity, fn(): ?BookingResult => $this->attemptForCustomer($userId, $slot, $capacity, $maxUpcoming, $now, $createdBy));
    }

    public function placeGuest(FullName $guest, ScheduledSlot $slot, int $capacity, int $staffId, DateTimeImmutable $now): BookingResult
    {
        return $this->retrying($slot, $capacity, fn(): ?BookingResult => $this->attemptForGuest($guest, $slot, $capacity, $staffId, $now));
    }

    public function find(int $id): ?Booking
    {
        $row = $this->database->fetchOne('SELECT ' . self::columns() . ' FROM bookings WHERE id = :id', ['id' => $id]);

        return $row === null ? null : self::booking($row);
    }

    public function findEntry(int $id): ?RosterEntry
    {
        $row = $this->database->fetchOne('SELECT ' . self::columns('b') . self::WITH_PERSON . ' WHERE b.id = :id', ['id' => $id]);

        return $row === null ? null : self::entry($row);
    }

    public function activeOn(int $userId, LocalDate $date): ?Booking
    {
        $row = $this->database->fetchOne(
            'SELECT ' . self::columns() . ' FROM bookings WHERE user_id = :user_id AND slot_date = :slot_date AND active = 1',
            ['user_id' => $userId, 'slot_date' => $date->value],
        );

        return $row === null ? null : self::booking($row);
    }

    public function activeFrom(int $userId, LocalDate $from): array
    {
        return array_map(self::booking(...), $this->database->fetchAll(
            'SELECT ' . self::columns() . ' FROM bookings WHERE user_id = :user_id AND active = 1 AND slot_date >= :slot_date ORDER BY starts_at, id',
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

    public function roster(LocalDate $date): array
    {
        return array_map(self::entry(...), $this->database->fetchAll(
            'SELECT ' . self::columns('b') . self::WITH_PERSON . ' WHERE b.slot_date = :slot_date AND b.active = 1 ORDER BY b.slot_time, b.seat',
            ['slot_date' => $date->value],
        ));
    }

    public function cancel(int $id, DateTimeImmutable $at, CancelledBy $by): bool
    {
        return $this->database->execute(
            'UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = :cancelled_at, cancelled_by = :cancelled_by WHERE id = :id AND active = 1',
            ['cancelled_at' => UtcDateTime::format($at), 'cancelled_by' => $by->value, 'id' => $id],
        ) > 0;
    }

    /**
     * I tentativi di prendere un posto, ognuno in una transazione nuova: un tentativo dà null quando il posto
     * scelto l'ha preso un altro nel frattempo, e il successivo rilegge tutto da capo.
     *
     * @param positive-int $capacity
     * @param Closure(): ?BookingResult $attempt
     */
    private function retrying(ScheduledSlot $slot, int $capacity, Closure $attempt): BookingResult
    {
        $attempts = $capacity + self::EXTRA_ATTEMPTS;
        for ($tried = 1; $tried <= $attempts; ++$tried) {
            try {
                $result = $this->database->transaction($attempt);
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

    /**
     * Un tentativo per un cliente registrato, dentro la transazione.
     *
     * @param positive-int $capacity
     * @param positive-int|null $maxUpcoming
     */
    private function attemptForCustomer(int $userId, ScheduledSlot $slot, int $capacity, ?int $maxUpcoming, DateTimeImmutable $now, CreatedBy $createdBy): ?BookingResult
    {
        // Prima di ogni lettura: da qui alla fine le altre prenotazioni di questo cliente aspettano.
        $this->lockUser($userId);

        $existing = $this->activeOn($userId, $slot->date);
        if ($existing !== null) {
            return BookingResult::alreadyBooked($slot, $existing);
        }
        if ($maxUpcoming !== null && $this->countUpcoming($userId, $now) >= $maxUpcoming) {
            return BookingResult::tooManyUpcoming($slot);
        }

        return $this->insert($slot, $capacity, ['user_id' => $userId, 'guest_name' => null], $createdBy, $now);
    }

    /**
     * Un tentativo per una persona senza account, dentro la transazione.
     *
     * @param positive-int $capacity
     */
    private function attemptForGuest(FullName $guest, ScheduledSlot $slot, int $capacity, int $staffId, DateTimeImmutable $now): ?BookingResult
    {
        // Come per i clienti, prima di ogni lettura: le prenotazioni inserite da questo titolare aspettano
        // (e su SQLite la transazione scrive da subito: una seconda aspetta invece di fallire).
        $this->lockUser($staffId);

        $existing = $this->guestIn($slot, $guest);
        if ($existing !== null) {
            return BookingResult::alreadyBooked($slot, $existing);
        }

        return $this->insert($slot, $capacity, ['user_id' => null, 'guest_name' => $guest->value], CreatedBy::Admin, $now);
    }

    /** Blocca la riga dell'utente in users fino alla fine della transazione: le sue prenotazioni passano una alla volta. */
    private function lockUser(int $userId): void
    {
        $this->database->execute('UPDATE users SET updated_at = updated_at WHERE id = :id', ['id' => $userId]);
    }

    /** La prenotazione attiva della persona senza account con quel nome, in quella fascia. */
    private function guestIn(ScheduledSlot $slot, FullName $guest): ?Booking
    {
        $row = $this->database->fetchOne(
            'SELECT ' . self::columns() . ' FROM bookings WHERE slot_date = :slot_date AND slot_time = :slot_time AND guest_name = :guest_name AND active = 1',
            ['slot_date' => $slot->date->value, 'slot_time' => $slot->time->value, 'guest_name' => $guest->value],
        );

        return $row === null ? null : self::booking($row);
    }

    /**
     * Scrive la prenotazione sul primo posto libero. Null se nel frattempo un'altra prenotazione ha preso il posto
     * (o, per una persona senza account, lo stesso nome nella fascia): il vincolo UNIQUE respinge la riga e si
     * riprova da capo.
     *
     * @param positive-int $capacity
     * @param array{user_id: int|null, guest_name: string|null} $person
     */
    private function insert(ScheduledSlot $slot, int $capacity, array $person, CreatedBy $createdBy, DateTimeImmutable $now): ?BookingResult
    {
        $seat = $this->seats->choose($this->seatsOf($slot), $capacity);
        if ($seat === null) {
            return BookingResult::full($slot);
        }

        try {
            $this->database->execute(
                'INSERT INTO bookings (user_id, guest_name, slot_date, slot_time, starts_at, seat, active, created_at, created_by)'
                . ' VALUES (:user_id, :guest_name, :slot_date, :slot_time, :starts_at, :seat, 1, :created_at, :created_by)',
                [
                    'user_id' => $person['user_id'],
                    'guest_name' => $person['guest_name'],
                    'slot_date' => $slot->date->value,
                    'slot_time' => $slot->time->value,
                    'starts_at' => UtcDateTime::format($slot->startsAt),
                    'seat' => $seat,
                    'created_at' => UtcDateTime::format($now),
                    'created_by' => $createdBy->value,
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

    /** "id, user_id, …"; con l'alias di una JOIN "b.id AS id, b.user_id AS user_id, …" (nomi delle colonne certi su ogni motore). */
    private static function columns(string $alias = ''): string
    {
        return implode(', ', array_map(
            static fn(string $column): string => $alias === '' ? $column : $alias . '.' . $column . ' AS ' . $column,
            self::COLUMNS,
        ));
    }

    private static function booking(Row $row): Booking
    {
        $createdBy = $row->string('created_by');
        $cancelledBy = $row->nullableString('cancelled_by');

        return new Booking(
            id: $row->int('id'),
            userId: $row->nullableInt('user_id'),
            guestName: $row->nullableString('guest_name'),
            date: self::date($row),
            time: self::time($row),
            startsAt: $row->dateTime('starts_at'),
            seat: $row->nullableInt('seat'),
            createdAt: $row->dateTime('created_at'),
            createdBy: CreatedBy::tryFrom($createdBy)
                ?? throw new UnexpectedValueException(sprintf('Autore della prenotazione sconosciuto nel database: "%s".', $createdBy)),
            cancelledAt: $row->nullableDateTime('cancelled_at'),
            cancelledBy: $cancelledBy === null ? null : (CancelledBy::tryFrom($cancelledBy)
                ?? throw new UnexpectedValueException(sprintf('Autore della disdetta sconosciuto nel database: "%s".', $cancelledBy))),
        );
    }

    /**
     * La prenotazione con la sua persona. L'errore, se l'email nel database non è valida, non la ripete:
     * nel log non finiscono dati personali.
     */
    private static function entry(Row $row): RosterEntry
    {
        $booking = self::booking($row);
        if ($booking->guestName !== null) {
            return new RosterEntry($booking, $booking->guestName, null);
        }

        return new RosterEntry(
            $booking,
            $row->string('full_name'),
            EmailAddress::tryFrom($row->string('email'))
                ?? throw new UnexpectedValueException(sprintf('Email non valida nel database per la prenotazione %d.', $booking->id)),
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
