<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\BookingUrls;
use FourStreet\Clock\Clock;
use FourStreet\Http\Response;
use FourStreet\Text\ItalianDateFormatter;

/**
 * La pagina di una fascia che non si può prenotare (templates/pages/booking/blocked.php), con il codice giusto:
 * 404 se la fascia non c'è (o i parametri sono manomessi), 409 se c'è ma lo stato non lo permette
 * (piena, già prenotato quel giorno, troppe prenotazioni future, chiusa), 429 dopo troppi tentativi.
 */
final readonly class BlockedBookingPage
{
    public function __construct(
        private AccountPages $pages,
        private BookingCalendar $calendar,
        private ItalianDateFormatter $dates,
        private Clock $clock,
    ) {}

    public function render(BookingResult $result): Response
    {
        return $this->pages->render('blocked', $this->data($result), $result->status === BookingStatus::NotFound ? 404 : 409);
    }

    /** La stessa pagina dopo troppi tentativi: 429 e Retry-After. */
    public function tooManyAttempts(BookingResult $result, int $retryAfter): Response
    {
        return $this->pages->tooManyAttempts('blocked', $this->data($result), $retryAfter);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(BookingResult $result): array
    {
        $found = !in_array($result->status, [BookingStatus::NotFound, BookingStatus::Throttled], true);
        $slot = $found ? $result->slot() : null;
        $existing = $result->status === BookingStatus::AlreadyBooked ? $result->booking() : null;
        $rules = $this->calendar->rules();

        return [
            'status' => $result->status,
            'slotDay' => $slot === null ? null : $this->dates->format($slot->date->calendarDay(), withYear: false, capitalize: false),
            'slotTime' => $slot?->time->value,
            'existingTime' => $existing?->time->value,
            // La prenotazione di quel giorno è già iniziata (o la disdetta è chiusa): la pagina non invita a disdirla.
            'existingCancellable' => $existing !== null && $this->calendar->canCancel($existing->startsAt, $this->clock->now()),
            'maxUpcoming' => $rules->maxUpcoming,
            'bookingDeadline' => $rules->bookingDeadline(),
            'dayUrl' => BookingUrls::day($slot?->date),
            'alert' => null,
        ];
    }
}
