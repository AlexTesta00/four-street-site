<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use LogicException;

/**
 * Una prenotazione, come sta nel database: il cliente, la fascia (giorno e orario locali), il posto.
 * Disdetta, libera il posto (seat null) e resta come storico, con quando e chi l'ha disdetta.
 * Gli istanti sono in UTC.
 */
final readonly class Booking
{
    /**
     * @param DateTimeImmutable $startsAt l'inizio della fascia, scritto alla prenotazione
     * @param int|null $seat il posto, da 1; null se la prenotazione è disdetta
     */
    public function __construct(
        public int $id,
        public int $userId,
        public LocalDate $date,
        public SlotTime $time,
        public DateTimeImmutable $startsAt,
        public ?int $seat,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?CancelledBy $cancelledBy = null,
    ) {}

    public function isActive(): bool
    {
        return $this->cancelledAt === null;
    }

    public function belongsTo(int $userId): bool
    {
        return $this->userId === $userId;
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
