<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRepository;
use FourStreet\Clock\Clock;

/**
 * Il mese visto dalla palestra, per il calendario del pannello: per ogni giorno l'orario, quante fasce, quante persone,
 * le fasce piene e le prenotazioni fuori dall'orario. Il database si legge una volta per le prenotazioni (contate per
 * fascia dal database stesso) e una volta per l'orario del mese: mai una query per giorno.
 */
final readonly class MonthOverview
{
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private Clock $clock,
    ) {}

    /** Il mese chiesto (senza, quello di oggi), oppure null se esce dalla portata del pannello (BookingCalendar::isWithinReach()). */
    public function month(?Month $requested): ?MonthSheet
    {
        $now = $this->clock->now();
        $today = $this->calendar->today($now);
        $current = Month::of($today);
        $month = $requested ?? $current;
        if (!$this->calendar->isWithinReach($month->first(), $now) || !$this->calendar->isWithinReach($month->last(), $now)) {
            return null;
        }

        $counts = $this->bookings->slotCounts($month->first(), $month->last());
        $capacity = $this->calendar->rules()->capacity;
        $openingDay = $this->calendar->openingDay();

        $days = [];
        for ($date = $month->first(); !$date->isAfter($month->last()); $date = $date->addDays(1)) {
            $slots = $this->calendar->slotsOn($date);
            $scheduled = [];
            $full = 0;
            foreach ($slots as $slot) {
                $scheduled[$slot->time->value] = true;
                if ($counts->of($date, $slot->time) >= $capacity) {
                    ++$full;
                }
            }
            $booked = 0;
            $outOfSchedule = 0;
            foreach ($counts->on($date) as $time => $taken) {
                $booked += $taken;
                if (!isset($scheduled[$time])) {
                    $outOfSchedule += $taken;
                }
            }
            $days[] = new MonthDay(
                $date,
                $this->calendar->plan($date),
                count($slots),
                $booked,
                $full,
                $outOfSchedule,
                $date->equals($today),
                $date->isBefore($today),
                $date->isBefore($openingDay),
            );
        }

        $previous = $month->previous();
        $next = $month->next();

        return new MonthSheet(
            $month,
            $current,
            $today,
            $days,
            $this->calendar->isWithinReach($previous->first(), $now) ? $previous : null,
            $this->calendar->isWithinReach($next->last(), $now) ? $next : null,
            $capacity,
        );
    }
}
