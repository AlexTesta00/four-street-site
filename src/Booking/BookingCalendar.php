<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Config\SiteConfig;

/**
 * Il calendario delle fasce: quali esistono e quando si possono prenotare o disdire. Non legge il database.
 *
 * Una fascia esiste se il suo giorno della settimana ha quell'orario (booking.schedule), il giorno non è
 * di chiusura (booking.closed_dates), comincia dall'inaugurazione in poi (opening) e quel giorno l'ora
 * esiste (il passaggio all'ora legale salta le 02:00-03:00). Giorni e orari sono ora locale del sito;
 * i confronti con "adesso" si fanno sugli istanti, così il cambio dell'ora non sposta nulla.
 */
final readonly class BookingCalendar
{
    /** Quanti anni prima e dopo quello di oggi arriva il titolare, nel suo pannello. */
    private const int REACH_YEARS = 10;

    public function __construct(
        private BookingRules $rules,
        private DateTimeZone $timezone,
        private DateTimeImmutable $opening,
    ) {}

    public static function forSite(SiteConfig $site): self
    {
        return new self($site->booking, $site->timezone, $site->opening);
    }

    public function rules(): BookingRules
    {
        return $this->rules;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /** Il giorno di oggi, nel fuso del sito. */
    public function today(DateTimeImmutable $now): LocalDate
    {
        return LocalDate::of($now, $this->timezone);
    }

    /** Il giorno dell'inaugurazione: prima non ci sono fasce. */
    public function openingDay(): LocalDate
    {
        return LocalDate::of($this->opening, $this->timezone);
    }

    /**
     * I giorni che si prenotano adesso: da oggi a fra days_ahead giorni, ma non prima del giorno
     * dell'inaugurazione. Ci sono anche i giorni senza fasce (domenica, chiusure), per mostrarli chiusi.
     * Vuoto solo se l'inaugurazione è ancora più lontana.
     *
     * @return list<LocalDate>
     */
    public function days(DateTimeImmutable $now): array
    {
        $today = $this->today($now);
        $last = $today->addDays($this->rules->daysAhead);
        $openingDay = $this->openingDay();

        $days = [];
        for ($day = $openingDay->isAfter($today) ? $openingDay : $today; !$day->isAfter($last); $day = $day->addDays(1)) {
            $days[] = $day;
        }

        return $days;
    }

    /** Il giorno è fra oggi e fra days_ahead giorni. */
    public function isBookableDay(LocalDate $day, DateTimeImmutable $now): bool
    {
        $today = $this->today($now);

        return !$day->isBefore($today) && !$day->isAfter($today->addDays($this->rules->daysAhead));
    }

    /** Il giorno viene dopo l'ultimo prenotabile: le sue fasce non sono ancora pubblicate. */
    public function isBeyondBookableDays(LocalDate $day, DateTimeImmutable $now): bool
    {
        return $day->isAfter($this->today($now)->addDays($this->rules->daysAhead));
    }

    /**
     * Il giorno è a portata del titolare: dal 1° gennaio di dieci anni fa al 31 dicembre fra dieci anni. Più in là
     * non c'è nulla da guardare né da prenotare, e così anche il giorno prima e quello dopo sono sempre giorni veri
     * del calendario (mai un anno 0 o 10000).
     */
    public function isWithinReach(LocalDate $day, DateTimeImmutable $now): bool
    {
        $year = (int) $this->today($now)->calendarDay()->format('Y');
        $first = LocalDate::tryFrom(sprintf('%04d-01-01', $year - self::REACH_YEARS));
        $last = LocalDate::tryFrom(sprintf('%04d-12-31', $year + self::REACH_YEARS));

        return $first !== null && $last !== null && !$day->isBefore($first) && !$day->isAfter($last);
    }

    /**
     * Le fasce di quel giorno, dalla prima.
     *
     * @return list<ScheduledSlot>
     */
    public function slotsOn(LocalDate $day): array
    {
        if ($this->rules->isClosedOn($day)) {
            return [];
        }

        $slots = [];
        foreach ($this->rules->schedule->on($day->weekday()) as $time) {
            $startsAt = $day->at($time, $this->timezone);
            // L'ora che il cambio dell'ora salta non esiste; prima dell'inaugurazione non si prenota.
            if ($startsAt === null || $startsAt < $this->opening) {
                continue;
            }
            $slots[] = new ScheduledSlot($day, $time, $startsAt, $this->endOf($startsAt));
        }

        return $slots;
    }

    /** La fascia di quel giorno a quell'ora, se esiste. */
    public function slot(LocalDate $day, SlotTime $time): ?ScheduledSlot
    {
        foreach ($this->slotsOn($day) as $slot) {
            if ($slot->time->equals($time)) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * La fascia si prenota ancora: il giorno è fra quelli prenotabili e manca più di
     * booking_closes_minutes_before all'inizio (con 0, fino all'inizio).
     */
    public function canBook(ScheduledSlot $slot, DateTimeImmutable $now): bool
    {
        return $this->isBookableDay($slot->date, $now)
            && $now < self::minutesBefore($slot->startsAt, $this->rules->bookingClosesMinutesBefore);
    }

    /** Una prenotazione che inizia a $startsAt si disdice ancora: manca più di cancel_closes_minutes_before. */
    public function canCancel(DateTimeImmutable $startsAt, DateTimeImmutable $now): bool
    {
        return $now < self::minutesBefore($startsAt, $this->rules->cancelClosesMinutesBefore);
    }

    /** La fine di una fascia che inizia a $startsAt: slot_minutes di tempo reale dopo, nel fuso del sito. */
    public function endOf(DateTimeImmutable $startsAt): DateTimeImmutable
    {
        return $this->local($startsAt->setTimestamp($startsAt->getTimestamp() + $this->rules->slotMinutes * 60));
    }

    /** Un istante nel fuso del sito, per mostrarlo. */
    public function local(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone($this->timezone);
    }

    /**
     * Le fasce del giorno come le vede chi sceglie: i posti presi e quelle che non si prenotano più.
     *
     * @return list<Slot>
     */
    public function seating(LocalDate $day, Occupancy $occupancy, DateTimeImmutable $now): array
    {
        $slots = [];
        foreach ($this->slotsOn($day) as $slot) {
            $slots[] = new Slot($slot->time->value, $this->rules->capacity, $occupancy->seatsOf($slot->date, $slot->time), !$this->canBook($slot, $now));
        }

        return $slots;
    }

    private static function minutesBefore(DateTimeImmutable $moment, int $minutes): DateTimeImmutable
    {
        return $moment->setTimestamp($moment->getTimestamp() - $minutes * 60);
    }
}
