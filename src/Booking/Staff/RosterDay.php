<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\DayPlan;

/**
 * Il giorno nel pannello del titolare: le sue fasce con chi è prenotato, il giorno prima e quello dopo
 * (anche nel passato, da guardare soltanto), la scelta rapida dei giorni prenotabili e l'orario scritto
 * del giorno (settimana tipo, orario suo o chiusura, con la nota del titolare).
 */
final readonly class RosterDay
{
    /**
     * @param list<RosterSlot> $slots le fasce del giorno, dalla prima; vuoto per un giorno senza fasce
     * @param list<DayChoice> $choices i giorni della scelta rapida: da oggi ai giorni prenotabili
     * @param LocalDate $openingDay il giorno dell'inaugurazione: prima non ci sono fasce
     * @param LocalDate|null $previous il giorno prima; null al bordo della portata del pannello
     * @param LocalDate|null $next il giorno dopo; null al bordo della portata del pannello
     * @param DayPlan $plan l'orario scritto del giorno e da dove viene; la nota è a uso interno del titolare
     */
    public function __construct(
        public LocalDate $date,
        public LocalDate $today,
        public LocalDate $openingDay,
        public array $slots,
        public array $choices,
        public ?LocalDate $previous,
        public ?LocalDate $next,
        public DayPlan $plan,
    ) {}

    public function isToday(): bool
    {
        return $this->date->equals($this->today);
    }

    /** Un giorno passato: si guarda soltanto. */
    public function isPast(): bool
    {
        return $this->date->isBefore($this->today);
    }

    public function isBeforeOpening(): bool
    {
        return $this->date->isBefore($this->openingDay);
    }
}
