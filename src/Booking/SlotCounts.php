<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Quante prenotazioni attive ha ogni fascia di alcuni giorni, già contate dal database in una lettura sola:
 * il mese del pannello del titolare, la settimana dell'orario.
 */
final readonly class SlotCounts
{
    /**
     * @param array<string, array<string, positive-int>> $counts giorno ("2026-12-07") => orario ("18:30") => prenotazioni attive
     */
    public function __construct(
        private array $counts = [],
    ) {}

    /** Le prenotazioni attive di quella fascia; 0 se non ne ha, o se non è fra quelle lette. */
    public function of(LocalDate $date, SlotTime $time): int
    {
        return $this->counts[$date->value][$time->value] ?? 0;
    }

    /**
     * Le fasce di quel giorno che hanno prenotazioni attive: orario => quante, dalla prima.
     *
     * @return array<string, positive-int>
     */
    public function on(LocalDate $date): array
    {
        return $this->counts[$date->value] ?? [];
    }
}
