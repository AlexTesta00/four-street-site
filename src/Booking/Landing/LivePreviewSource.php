<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\DayState;
use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\SlotBoard;
use FourStreet\Text\ItalianDateFormatter;

/**
 * Le fasce vere del primo giorno prenotabile con un posto libero (o, se sono tutte piene, del primo
 * giorno pieno). Poche letture del database (le regole e l'orario, poi i posti dei giorni prenotabili):
 * niente sessione, niente visitatore, la landing è uguale per tutti. Se nei prossimi giorni non c'è nulla
 * da prenotare (fasce passate, giorni chiusi) la landing lo dice, senza mostrare fasce che non si scelgono.
 * Se il titolare ha spento le prenotazioni dal pannello, le fasce di esempio, come con booking.online a false.
 */
final readonly class LivePreviewSource implements SlotPreviewSource
{
    /**
     * @param BookingCalendar $calendar lo stesso del tabellone, con le regole in vigore
     */
    public function __construct(
        private SlotBoard $board,
        private BookingCalendar $calendar,
        private ExampleSlotCatalog $examples,
        private ItalianDateFormatter $dates,
    ) {}

    public function preview(): SlotPreview
    {
        if (!$this->calendar->rules()->online) {
            return $this->examples;
        }

        $board = $this->board->day(null, null);
        $day = $board->selected;
        if ($day === null || $day->state() === DayState::Closed) {
            return new LivePreview($board->capacity, null, '', []);
        }

        return new LivePreview(
            $board->capacity,
            $day->date,
            $this->dates->format($day->date->calendarDay(), withYear: false, capitalize: false),
            $day->slots,
        );
    }

    public function isLive(): bool
    {
        return true;
    }
}
