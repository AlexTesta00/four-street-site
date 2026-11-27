<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\SlotTime;
use FourStreet\Clock\Clock;
use FourStreet\Text\ItalianDateFormatter;

/**
 * Gli orari del primo giorno con una fascia ancora da prenotare, calcolati dal solo calendario:
 * la landing senza numeri quando il database non risponde.
 */
final readonly class TimetablePreviewSource implements SlotPreviewSource
{
    public function __construct(
        private BookingCalendar $calendar,
        private ItalianDateFormatter $dates,
        private Clock $clock,
    ) {}

    public function preview(): SlotPreview
    {
        $now = $this->clock->now();
        $capacity = $this->calendar->rules()->capacity;
        foreach ($this->calendar->days($now) as $day) {
            $open = array_values(array_filter($this->calendar->slotsOn($day), fn(ScheduledSlot $slot): bool => $this->calendar->canBook($slot, $now)));
            if ($open !== []) {
                return new TimetablePreview(
                    $capacity,
                    $day,
                    $this->dates->format($day->calendarDay(), withYear: false, capitalize: false),
                    array_map(static fn(ScheduledSlot $slot): SlotTime => $slot->time, $open),
                );
            }
        }

        return new TimetablePreview($capacity, null, '', []);
    }

    public function isLive(): bool
    {
        return true;
    }
}
