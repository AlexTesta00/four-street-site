<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;

/**
 * La pagina Disdette del pannello: le prenotazioni disdette dalla palestra con la fascia ancora da cominciare, divise per
 * giorno, ognuna con la sua persona e com'è andata l'email della palestra (Booking::$gymEmail).
 */
final readonly class CancellationSheet
{
    /**
     * @param LocalDate $today oggi, per scrivere i giorni (con l'anno solo quando non è quello di oggi)
     * @param list<CancellationDay> $days dal primo
     */
    public function __construct(
        public LocalDate $today,
        public array $days,
    ) {}

    /**
     * @param list<RosterEntry> $entries per giorno, fascia e numero, come le legge BookingRepository::upcomingGymCancellations()
     */
    public static function of(LocalDate $today, array $entries): self
    {
        $byDay = [];
        foreach ($entries as $entry) {
            $byDay[$entry->booking->date->value][] = $entry;
        }

        $days = [];
        foreach ($byDay as $dayEntries) {
            $days[] = new CancellationDay($dayEntries[0]->booking->date, $dayEntries);
        }

        return new self($today, $days);
    }

    /** Quante prenotazioni disdette, in tutto. */
    public function count(): int
    {
        return array_sum(array_map(static fn(CancellationDay $day): int => count($day->entries), $this->days));
    }

    /** Quante email si mandano dal pannello: quelle in attesa e quelle non partite (GymEmail::canBeSent()). */
    public function toSend(): int
    {
        $count = 0;
        foreach ($this->days as $day) {
            foreach ($day->entries as $entry) {
                if ($entry->booking->gymEmail?->canBeSent() ?? false) {
                    ++$count;
                }
            }
        }

        return $count;
    }
}
