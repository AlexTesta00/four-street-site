<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;

/**
 * Una settimana, da lunedì a domenica, come la cambia il titolare: i sette giorni con il loro orario, la settimana prima
 * e quella dopo (null al bordo della portata del pannello) e se si può copiare nella successiva.
 */
final readonly class WeekSheet
{
    /**
     * @param list<WeekSheetDay> $days da lunedì a domenica
     * @param bool $canCopy la settimana dopo non è ancora cominciata: questa si copia lì
     */
    public function __construct(
        public LocalDate $monday,
        public LocalDate $today,
        public LocalDate $openingDay,
        public array $days,
        public ?LocalDate $previous,
        public ?LocalDate $next,
        public bool $canCopy,
    ) {}

    public function sunday(): LocalDate
    {
        return $this->monday->addDays(6);
    }

    /** La settimana di oggi. */
    public function isCurrent(): bool
    {
        return !$this->today->isBefore($this->monday) && !$this->today->isAfter($this->sunday());
    }

    /** Il lunedì della settimana di oggi. */
    public function currentMonday(): LocalDate
    {
        return $this->today->addDays(1 - $this->today->weekday()->value);
    }
}
