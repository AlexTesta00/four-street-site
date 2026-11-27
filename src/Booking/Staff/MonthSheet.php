<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Weekday;

/**
 * Il mese nel pannello del titolare: ogni giorno con il suo orario e le sue prenotazioni, il mese prima e quello dopo
 * (null al bordo della portata del pannello).
 */
final readonly class MonthSheet
{
    /**
     * @param Month $current il mese di oggi
     * @param list<MonthDay> $days i giorni del mese, dal primo
     * @param positive-int $capacity i posti di ogni fascia
     */
    public function __construct(
        public Month $month,
        public Month $current,
        public LocalDate $today,
        public array $days,
        public ?Month $previous,
        public ?Month $next,
        public int $capacity,
    ) {}

    public function isCurrent(): bool
    {
        return $this->month->equals($this->current);
    }

    /**
     * I giorni divisi per settimana, da lunedì a domenica: la prima e l'ultima settimana del mese possono essere più corte.
     *
     * @return list<non-empty-list<MonthDay>>
     */
    public function weeks(): array
    {
        $weeks = [];
        $week = [];
        foreach ($this->days as $day) {
            if ($week !== [] && $day->date->weekday() === Weekday::Monday) {
                $weeks[] = $week;
                $week = [];
            }
            $week[] = $day;
        }
        if ($week !== []) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /** Le prenotazioni attive del mese. */
    public function booked(): int
    {
        return array_sum(array_map(static fn(MonthDay $day): int => $day->booked, $this->days));
    }

    /** Le prenotazioni del mese in fasce che l'orario non ha più: da guardare (e disdire) nel giorno. */
    public function outOfSchedule(): int
    {
        return array_sum(array_map(static fn(MonthDay $day): int => $day->outOfSchedule, $this->days));
    }
}
