<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * I posti presi delle fasce di alcuni giorni, letti dal database in una volta sola.
 */
final readonly class Occupancy
{
    /**
     * @param array<string, list<int>> $seats chiave della fascia ("2026-12-07 18:30") => posti presi, in ordine
     */
    public function __construct(
        private array $seats = [],
    ) {}

    /**
     * I posti presi di quella fascia; nessuno se la fascia non è fra quelle lette.
     *
     * @return list<int>
     */
    public function seatsOf(LocalDate $date, SlotTime $time): array
    {
        return $this->seats[ScheduledSlot::keyOf($date, $time)] ?? [];
    }
}
