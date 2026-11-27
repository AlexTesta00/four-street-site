<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Schedule\ScheduleStore;
use FourStreet\Clock\Clock;

/**
 * L'orario come lo legge il titolare per cambiarlo: la settimana tipo, una settimana precisa con le persone prenotate
 * per fascia, le chiusure in programma. Senza HTTP; le modifiche le fa Schedule\ScheduleEditor.
 * Una settimana legge le prenotazioni una volta sola (contate per fascia) e l'orario del suo mese (o dei suoi due mesi).
 */
final readonly class ScheduleBoard
{
    /**
     * @param BookingCalendar $calendar il calendario sulla stessa fonte dell'orario ($schedule)
     */
    public function __construct(
        private BookingCalendar $calendar,
        private ScheduleStore $schedule,
        private BookingRepository $bookings,
        private Clock $clock,
    ) {}

    /** La settimana tipo di adesso, che vale da domani. */
    public function template(): TemplateSheet
    {
        return new TemplateSheet(
            $this->schedule->week(),
            $this->calendar->today($this->clock->now())->addDays(1),
            $this->calendar->rules()->slotMinutes,
        );
    }

    /**
     * La settimana di quel giorno (da lunedì a domenica; senza giorno, quella di oggi), oppure null se esce dalla
     * portata del pannello (BookingCalendar::isWithinReach()).
     */
    public function week(?LocalDate $day): ?WeekSheet
    {
        $now = $this->clock->now();
        $today = $this->calendar->today($now);
        $day ??= $today;
        $monday = $day->addDays(1 - $day->weekday()->value);
        $sunday = $monday->addDays(6);
        if (!$this->calendar->isWithinReach($monday, $now) || !$this->calendar->isWithinReach($sunday, $now)) {
            return null;
        }

        $counts = $this->bookings->slotCounts($monday, $sunday);
        $openingDay = $this->calendar->openingDay();
        $days = [];
        for ($date = $monday; !$date->isAfter($sunday); $date = $date->addDays(1)) {
            $plan = $this->calendar->plan($date);
            $existing = [];
            foreach ($this->calendar->slotsOn($date) as $slot) {
                $existing[$slot->time->value] = true;
            }
            $times = [];
            foreach ($plan->times as $time) {
                $start = $this->calendar->startOf($date, $time);
                $times[] = new DayTime(
                    $time,
                    $counts->of($date, $time),
                    $date->isBefore($today) || ($start !== null && $start <= $now),
                    isset($existing[$time->value]),
                );
            }
            $outOfSchedule = 0;
            foreach ($counts->on($date) as $time => $taken) {
                if (!isset($existing[$time])) {
                    $outOfSchedule += $taken;
                }
            }
            $days[] = new WeekSheetDay(
                $date,
                $plan,
                $this->schedule->weekTimesOn($date),
                $times,
                $outOfSchedule,
                $date->equals($today),
                $date->isBefore($today),
                $date->isBefore($openingDay),
            );
        }

        $previous = $monday->addDays(-7);
        $next = $monday->addDays(7);

        return new WeekSheet(
            $monday,
            $today,
            $openingDay,
            $days,
            $this->calendar->isWithinReach($previous, $now) ? $previous : null,
            $this->calendar->isWithinReach($next->addDays(6), $now) ? $next : null,
            $next->isAfter($today) && $this->calendar->isWithinReach($next->addDays(6), $now),
        );
    }

    /**
     * Le chiusure da oggi in poi, dalla prima: i giorni di fila con la stessa nota in un intervallo solo ("dal 24 al 26").
     *
     * @return list<ClosureRange>
     */
    public function closures(): array
    {
        $ranges = [];
        foreach ($this->schedule->closuresFrom($this->calendar->today($this->clock->now())) as $day) {
            $last = $ranges === [] ? null : $ranges[array_key_last($ranges)];
            if ($last !== null && $last->to->addDays(1)->equals($day->date) && $last->note === $day->note) {
                $ranges[array_key_last($ranges)] = $last->through($day->date);

                continue;
            }
            $ranges[] = new ClosureRange($day->date, $day->date, $day->note);
        }

        return $ranges;
    }

    /**
     * Le prenotazioni con quei numeri disdette dalla palestra, con le loro persone: per l'avviso dopo una modifica
     * dell'orario, che nell'indirizzo porta solo i numeri. Un numero di una prenotazione attiva, o disdetta dal cliente,
     * non c'entra con la modifica e non compare.
     *
     * @param list<int> $ids
     *
     * @return list<RosterEntry>
     */
    public function cancelled(array $ids): array
    {
        return array_values(array_filter(
            $this->bookings->findEntries($ids),
            static fn(RosterEntry $entry): bool => $entry->booking->cancelledBy === CancelledBy::Admin,
        ));
    }
}
