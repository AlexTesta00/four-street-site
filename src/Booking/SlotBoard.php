<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\User;
use FourStreet\Clock\Clock;

/**
 * Le fasce dei giorni prenotabili con i posti veri, per la pagina /prenota/ e per la landing.
 * Per un ospite legge il database una volta sola (i posti dei giorni prenotabili); per chi ha fatto
 * l'accesso anche le sue prenotazioni.
 */
final readonly class SlotBoard
{
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private Clock $clock,
    ) {}

    /**
     * Il giorno chiesto, se si prenota; altrimenti il primo con una fascia libera, oppure il primo tutto
     * pieno, oppure il primo con delle fasce (già chiuse), oppure il primo.
     */
    public function day(?LocalDate $requested, ?User $visitor): DayBoard
    {
        $now = $this->clock->now();
        $rules = $this->calendar->rules();
        $days = $this->calendar->days($now);
        if ($days === []) {
            return new DayBoard([], null, $requested !== null, 0, $rules->maxUpcoming, $rules->capacity);
        }

        $occupancy = $this->bookings->occupancy($days[0], $days[array_key_last($days)]);
        $mine = $visitor === null ? [] : $this->bookings->activeFrom($visitor->id, $this->calendar->today($now));

        $options = [];
        foreach ($days as $day) {
            $booking = self::bookingOn($mine, $day);
            $options[] = new DayOption(
                $day,
                $this->calendar->seating($day, $occupancy, $now),
                $booking,
                $booking !== null && $this->calendar->canCancel($booking->startsAt, $now),
            );
        }

        $selected = $requested === null ? null : self::find($options, $requested);
        $upcoming = array_filter($mine, static fn(Booking $booking): bool => $booking->startsAt > $now);

        return new DayBoard(
            $options,
            $selected ?? self::firstOpen($options),
            $requested !== null && $selected === null,
            count($upcoming),
            $rules->maxUpcoming,
            $rules->capacity,
        );
    }

    /**
     * Il primo giorno con una fascia libera; altrimenti il primo tutto pieno (si libera un posto quando
     * qualcuno disdice), poi il primo con delle fasce, poi il primo.
     *
     * @param non-empty-list<DayOption> $days
     */
    private static function firstOpen(array $days): DayOption
    {
        foreach ([DayState::Open, DayState::Full] as $state) {
            foreach ($days as $day) {
                if ($day->state() === $state) {
                    return $day;
                }
            }
        }
        foreach ($days as $day) {
            if ($day->hasSlots()) {
                return $day;
            }
        }

        return $days[0];
    }

    /**
     * @param list<DayOption> $days
     */
    private static function find(array $days, LocalDate $date): ?DayOption
    {
        foreach ($days as $day) {
            if ($day->date->equals($date)) {
                return $day;
            }
        }

        return null;
    }

    /**
     * @param list<Booking> $bookings
     */
    private static function bookingOn(array $bookings, LocalDate $date): ?Booking
    {
        foreach ($bookings as $booking) {
            if ($booking->date->equals($date)) {
                return $booking;
            }
        }

        return null;
    }
}
