<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use DateTimeImmutable;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Clock\Clock;

/**
 * Il giorno visto dalla palestra, per il pannello del titolare: le fasce dell'orario con chi è prenotato
 * (nome, email se ha l'account, posto, chi l'ha inserita) e cosa si può ancora fare. Il database si legge
 * una volta sola per giorno: le prenotazioni attive con le loro persone (BookingRepository::roster()).
 *
 * Una prenotazione in una fascia che l'orario non ha più (orario cambiato, giorno chiuso dopo) si vede lo stesso,
 * nella sua fascia: il titolare la può ancora disdire, ma lì non aggiunge nessuno.
 */
final readonly class DayRoster
{
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private Clock $clock,
    ) {}

    /**
     * Il giorno chiesto; senza, oggi, oppure (se oggi non ha fasce) il primo giorno prenotabile che ne ha.
     * Null se il giorno chiesto è oltre la portata del pannello (BookingCalendar::isWithinReach()).
     */
    public function day(?LocalDate $requested): ?RosterDay
    {
        $now = $this->clock->now();
        if ($requested !== null && !$this->calendar->isWithinReach($requested, $now)) {
            return null;
        }

        $today = $this->calendar->today($now);
        $choices = array_map(
            fn(LocalDate $day): DayChoice => new DayChoice($day, $this->calendar->slotsOn($day) !== []),
            $this->calendar->days($now),
        );
        $date = $requested ?? $this->firstWithSlots($today, $choices);
        $previous = $date->addDays(-1);
        $next = $date->addDays(1);

        return new RosterDay(
            $date,
            $today,
            $this->calendar->openingDay(),
            $this->slots($date, $now),
            $choices,
            $this->calendar->isWithinReach($previous, $now) ? $previous : null,
            $this->calendar->isWithinReach($next, $now) ? $next : null,
        );
    }

    /** Una prenotazione con la sua persona, anche disdetta: per l'avviso dopo un redirect, che ne porta solo il numero. */
    public function entry(int $bookingId): ?RosterEntry
    {
        return $this->bookings->findEntry($bookingId);
    }

    /**
     * @return list<RosterSlot>
     */
    private function slots(LocalDate $date, DateTimeImmutable $now): array
    {
        $capacity = $this->calendar->rules()->capacity;
        $booked = [];
        foreach ($this->bookings->roster($date) as $entry) {
            $booked[$entry->booking->time->value][] = $entry;
        }

        $slots = [];
        foreach ($this->calendar->slotsOn($date) as $slot) {
            $slots[$slot->key()] = new RosterSlot($slot, $capacity, $booked[$slot->time->value] ?? [], SlotPhase::of($slot, $now));
            unset($booked[$slot->time->value]);
        }
        foreach ($booked as $entries) {
            $slot = $this->outOfSchedule($entries);
            $slots[$slot->key()] = new RosterSlot($slot, $capacity, $entries, SlotPhase::of($slot, $now), scheduled: false);
        }
        // "2026-12-07 07:00" < "2026-12-07 18:30": la chiave della fascia si ordina come testo.
        ksort($slots, SORT_STRING);

        return array_values($slots);
    }

    /**
     * La fascia di prenotazioni che l'orario non ha più: giorno, orario e inizio sono quelli scritti alla prenotazione.
     *
     * @param non-empty-list<RosterEntry> $entries
     */
    private function outOfSchedule(array $entries): ScheduledSlot
    {
        $booking = $entries[0]->booking;

        return new ScheduledSlot($booking->date, $booking->time, $this->calendar->local($booking->startsAt), $this->calendar->endOf($booking->startsAt));
    }

    /**
     * @param list<DayChoice> $choices
     */
    private function firstWithSlots(LocalDate $today, array $choices): LocalDate
    {
        if ($this->calendar->slotsOn($today) !== []) {
            return $today;
        }
        foreach ($choices as $choice) {
            if ($choice->hasSlots) {
                return $choice->date;
            }
        }

        return $today;
    }
}
