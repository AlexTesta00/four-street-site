<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Account\User;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotBooking;
use FourStreet\Booking\SlotTime;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /prenota/conferma/?giorno=…&fascia=…: solo dopo l'accesso (chi non l'ha fatto passa da accesso
 * o registrazione e torna qui). Con GET il riepilogo della fascia e il bottone "Conferma la prenotazione";
 * con POST (token CSRF) la prenotazione, poi 303 verso l'account: ricaricare non prenota due volte.
 * Lo stesso modulo inviato due volte porta comunque all'account, con la prenotazione che c'è già.
 */
final readonly class ConfirmBookingController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private SlotBooking $booking,
        private BlockedBookingPage $blocked,
        private BookingCalendar $calendar,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $customer = Visitor::of($request)->user();
        $submitted = $request->method === 'POST';
        $selection = $submitted ? SlotSelection::fromBody($request) : SlotSelection::fromQuery($request);

        if ($selection->tampered || ($submitted && ($selection->date === null || $selection->time === null))) {
            return $this->blocked->render(BookingResult::notFound());
        }
        if ($selection->date === null || $selection->time === null) {
            // Un indirizzo senza la fascia (o scritto a mano): la scelta si fa nella pagina delle fasce.
            return Response::seeOther(BookingUrls::day($selection->date));
        }
        if (!$submitted) {
            return $this->summary($request, $customer, $selection->date, $selection->time);
        }

        $result = $this->booking->book($customer, $selection->date, $selection->time, $request->remoteAddress);

        return match (true) {
            $result->status === BookingStatus::Booked => Response::seeOther(BookingUrls::account(
                $result->email() === EmailDelivery::Sent ? BookingNotice::Booked : BookingNotice::BookedWithoutEmail,
                $result->booking()->id,
            )),
            $result->isRepeat() => Response::seeOther(BookingUrls::account(BookingNotice::AlreadyYours, $result->booking()->id)),
            $result->status === BookingStatus::Throttled => $this->summary($request, $customer, $selection->date, $selection->time, $result->retryAfter),
            default => $this->blocked->render($result),
        };
    }

    /**
     * Il riepilogo, se la fascia si prenota; altrimenti la pagina che dice perché no.
     *
     * @param int|null $retryAfter dopo troppi tentativi: la stessa pagina con l'avviso, 429
     */
    private function summary(Request $request, User $customer, LocalDate $date, SlotTime $time, ?int $retryAfter = null): Response
    {
        $result = $this->booking->check($customer, $date, $time);
        if ($result->status !== BookingStatus::Available) {
            return $retryAfter === null ? $this->blocked->render($result) : $this->blocked->tooManyAttempts($result, $retryAfter);
        }

        $slot = $result->slot();
        $data = [
            'csrf' => CsrfToken::of($request)->value,
            'slot' => $slot,
            'seats' => $result->seats(),
            'day' => $this->dates->format($slot->date->calendarDay(), withYear: false),
            'email' => $customer->email->value,
            'cancellation' => $this->calendar->rules()->cancellationDeadline(),
            'backUrl' => BookingUrls::day($slot->date),
            'alert' => null,
        ];

        return $retryAfter === null ? $this->pages->render('confirm', $data) : $this->pages->tooManyAttempts('confirm', $data, $retryAfter);
    }
}
