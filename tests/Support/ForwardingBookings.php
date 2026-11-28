<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use DateTimeImmutable;
use FourStreet\Account\FullName;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Occupancy;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\SlotCounts;

/**
 * Le prenotazioni vere, passate così come sono: un test ne estende uno o due metodi per far succedere qualcosa
 * proprio in mezzo, come un'altra richiesta nello stesso istante.
 */
abstract class ForwardingBookings implements BookingRepository
{
    public function __construct(
        protected readonly BookingRepository $inner,
    ) {}

    public function place(int $userId, ScheduledSlot $slot, int $capacity, ?int $maxUpcoming, DateTimeImmutable $now, CreatedBy $createdBy): BookingResult
    {
        return $this->inner->place($userId, $slot, $capacity, $maxUpcoming, $now, $createdBy);
    }

    public function placeGuest(FullName $guest, ScheduledSlot $slot, int $capacity, int $staffId, DateTimeImmutable $now): BookingResult
    {
        return $this->inner->placeGuest($guest, $slot, $capacity, $staffId, $now);
    }

    public function find(int $id): ?Booking
    {
        return $this->inner->find($id);
    }

    public function findEntry(int $id): ?RosterEntry
    {
        return $this->inner->findEntry($id);
    }

    public function activeOn(int $userId, LocalDate $date): ?Booking
    {
        return $this->inner->activeOn($userId, $date);
    }

    public function activeFrom(int $userId, LocalDate $from): array
    {
        return $this->inner->activeFrom($userId, $from);
    }

    public function countUpcoming(int $userId, DateTimeImmutable $now): int
    {
        return $this->inner->countUpcoming($userId, $now);
    }

    public function occupancy(LocalDate $from, LocalDate $to): Occupancy
    {
        return $this->inner->occupancy($from, $to);
    }

    public function roster(LocalDate $date): array
    {
        return $this->inner->roster($date);
    }

    public function slotCounts(LocalDate $from, LocalDate $to): SlotCounts
    {
        return $this->inner->slotCounts($from, $to);
    }

    public function activeEntries(LocalDate $from, ?LocalDate $to = null): array
    {
        return $this->inner->activeEntries($from, $to);
    }

    public function findEntries(array $ids): array
    {
        return $this->inner->findEntries($ids);
    }

    public function upcomingGymCancellations(LocalDate $today, DateTimeImmutable $now): array
    {
        return $this->inner->upcomingGymCancellations($today, $now);
    }

    public function cancel(int $id, DateTimeImmutable $at, CancelledBy $by): bool
    {
        return $this->inner->cancel($id, $at, $by);
    }

    public function claimGymEmail(int $id): bool
    {
        return $this->inner->claimGymEmail($id);
    }

    public function settleGymEmail(int $id, GymEmail $result): void
    {
        $this->inner->settleGymEmail($id, $result);
    }
}
