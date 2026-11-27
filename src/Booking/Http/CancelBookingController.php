<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Account\User;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingCancellation;
use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\CancellationStatus;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /account/prenotazioni/{id}/disdici/: con GET chiede conferma (un link non disdice mai nulla), con POST
 * (token CSRF) disdice e torna all'account. Solo una prenotazione del cliente, attiva e ancora disdicibile:
 * per tutto il resto la pagina non esiste (404), senza dire se la prenotazione è di un altro. Il secondo
 * invio della stessa disdetta torna all'account, come il primo.
 */
final readonly class CancelBookingController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private BookingCancellation $cancellation,
        private BookingCalendar $calendar,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $customer = Visitor::of($request)->user();
        $id = BookingUrls::bookingId($request->attribute('id'));
        if ($id === null) {
            return $this->missing();
        }

        if ($request->method !== 'POST') {
            $booking = $this->cancellation->cancellable($customer, $id);

            return $booking === null ? $this->missing() : $this->pages->render('cancel', $this->data($request, $booking));
        }

        $result = $this->cancellation->cancel($customer, $id, $request->remoteAddress);

        return match ($result->status) {
            CancellationStatus::Cancelled => Response::seeOther(BookingUrls::account(
                $result->email() === EmailDelivery::Sent ? BookingNotice::Cancelled : BookingNotice::CancelledWithoutEmail,
                $result->booking()->id,
            )),
            // Lo stesso modulo inviato di nuovo: all'account, come la prima volta, senza un errore.
            CancellationStatus::AlreadyCancelled => Response::seeOther(BookingUrls::account(BookingNotice::AlreadyCancelled, $result->booking()->id)),
            CancellationStatus::Throttled => $this->throttled($request, $customer, $id, $result->retryAfter),
            CancellationStatus::NotFound => $this->missing(),
        };
    }

    /** Troppi tentativi: la stessa pagina di conferma con l'avviso, se la prenotazione si disdice ancora. */
    private function throttled(Request $request, User $customer, int $id, int $retryAfter): Response
    {
        $booking = $this->cancellation->cancellable($customer, $id);

        return $booking === null ? $this->missing() : $this->pages->tooManyAttempts('cancel', $this->data($request, $booking), $retryAfter);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, Booking $booking): array
    {
        return [
            'csrf' => CsrfToken::of($request)->value,
            'booking' => $booking,
            'day' => $this->dates->format($booking->date->calendarDay()),
            'endTime' => $this->calendar->endOf($booking->startsAt)->format('H:i'),
            'capacity' => $this->calendar->rules()->capacity,
            'action' => BookingUrls::cancel($booking->id),
            'alert' => null,
        ];
    }

    private function missing(): Response
    {
        return $this->pages->render('cancel-missing', [], 404);
    }
}
