<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use LogicException;

/**
 * Una prenotazione, come sta nel database: la persona, la fascia (giorno e orario locali), il posto.
 * La persona è un cliente registrato (userId) oppure, se l'ha inserita la palestra, una persona senza account
 * (guestName, il nome scritto dal titolare): sempre una delle due. Disdetta, libera il posto (seat null)
 * e resta come storico, con quando e chi l'ha disdetta e, se l'ha disdetta la palestra a un cliente registrato,
 * com'è andata l'email della palestra. Gli istanti sono in UTC.
 */
final readonly class Booking
{
    /**
     * @param int|null $userId il cliente registrato; null per una persona senza account
     * @param string|null $guestName il nome della persona senza account; null per un cliente registrato
     * @param DateTimeImmutable $startsAt l'inizio della fascia, scritto alla prenotazione
     * @param int|null $seat il posto, da 1; null se la prenotazione è disdetta
     * @param GymEmail|null $gymEmail l'email della palestra, quando a disdire è stata lei e il cliente ha l'account;
     *                                null per tutto il resto, comprese le disdette di prima che il sito ne tenesse traccia
     */
    public function __construct(
        public int $id,
        public ?int $userId,
        public ?string $guestName,
        public LocalDate $date,
        public SlotTime $time,
        public DateTimeImmutable $startsAt,
        public ?int $seat,
        public DateTimeImmutable $createdAt,
        public CreatedBy $createdBy,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?CancelledBy $cancelledBy = null,
        public ?GymEmail $gymEmail = null,
    ) {}

    public function isActive(): bool
    {
        return $this->cancelledAt === null;
    }

    /** È del cliente con quell'account (quella di una persona senza account non è di nessun account). */
    public function belongsTo(int $userId): bool
    {
        return $this->userId === $userId;
    }

    /** La persona non ha un account: l'ha inserita la palestra con il solo nome. */
    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    public function isFor(LocalDate $date, SlotTime $time): bool
    {
        return $this->date->equals($date) && $this->time->equals($time);
    }

    /** Il posto di una prenotazione attiva. */
    public function seat(): int
    {
        return $this->seat ?? throw new LogicException(sprintf('La prenotazione %d è disdetta: non ha più un posto.', $this->id));
    }
}
