<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;

/**
 * Come diventa la settimana tipo di un giorno della settimana dopo la modifica: gli orari di prima e di dopo,
 * e da quando vale (domani: oggi e il passato restano com'erano).
 */
final readonly class WeekdayWrite
{
    /**
     * @param list<SlotTime> $before gli orari di adesso
     * @param list<SlotTime> $after gli orari dopo la modifica, in ordine
     * @param LocalDate $from il primo giorno in cui vale
     */
    public function __construct(
        public Weekday $day,
        public array $before,
        public array $after,
        public LocalDate $from,
    ) {}

    /** Come per un giorno (DayWrite::removes()): spariscono gli orari tolti; tutti, se non ne resta nessuno. */
    public function removes(SlotTime $time): bool
    {
        return (new DayWrite($this->from, DayKind::Week, $this->before, $this->after))->removes($time);
    }
}
