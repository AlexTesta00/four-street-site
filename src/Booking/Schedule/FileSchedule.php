<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\BookingRules;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\WeeklySchedule;

/**
 * L'orario e le regole di config/site.php, così come sono: senza Backend, nei test che non usano il database
 * e per la landing quando il database non risponde (gli orari senza i posti).
 */
final readonly class FileSchedule implements ScheduleSource
{
    public function __construct(
        private BookingRules $rules,
    ) {}

    public function rules(): BookingRules
    {
        return $this->rules;
    }

    public function day(LocalDate $date): DayPlan
    {
        return $this->rules->isClosedOn($date)
            ? new DayPlan($date, DayKind::Closed, [])
            : new DayPlan($date, DayKind::Week, $this->rules->schedule->on($date->weekday()));
    }

    public function week(): WeeklySchedule
    {
        return $this->rules->schedule;
    }
}
