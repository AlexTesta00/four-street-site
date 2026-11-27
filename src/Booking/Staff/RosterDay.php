<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;

/**
 * Il giorno nel pannello del titolare: le sue fasce con chi è prenotato, il giorno prima e quello dopo
 * (anche nel passato, da guardare soltanto) e la scelta rapida dei giorni prenotabili.
 */
final readonly class RosterDay
{
    /**
     * @param list<RosterSlot> $slots le fasce del giorno, dalla prima; vuoto per un giorno senza fasce
     * @param list<DayChoice> $choices i giorni della scelta rapida: da oggi ai giorni prenotabili
     * @param LocalDate $openingDay il giorno dell'inaugurazione: prima non ci sono fasce
     * @param LocalDate|null $previous il giorno prima; null al bordo della portata del pannello
     * @param LocalDate|null $next il giorno dopo; null al bordo della portata del pannello
     */
    public function __construct(
        public LocalDate $date,
        public LocalDate $today,
        public LocalDate $openingDay,
        public array $slots,
        public array $choices,
        public ?LocalDate $previous,
        public ?LocalDate $next,
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
