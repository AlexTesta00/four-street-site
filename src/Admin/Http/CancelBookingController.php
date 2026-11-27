<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\CancellationStatus;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Staff\StaffCancellation;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /gestione/prenotazioni/{id}/disdici/: con GET chiede conferma (un link non disdice mai nulla), con POST (token CSRF)
 * disdice e torna al giorno, con l'avviso. Qualsiasi prenotazione attiva non ancora iniziata, di un cliente o di una
 * persona senza account; per il resto non c'è niente da disdire (404). Il secondo invio della stessa disdetta torna
 * al giorno come il primo, senza email.
 */
final readonly class CancelBookingController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private StaffCancellation $cancellation,
        private BookingCalendar $calendar,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $id = BookingUrls::bookingId($request->attribute('id'));
        if ($id === null) {
            return $this->missing();
        }

        if ($request->method !== 'POST') {
            $entry = $this->cancellation->cancellable($id);

            return $entry === null ? $this->missing() : $this->pages->render('cancel', $this->data($request, $entry));
        }

        $result = $this->cancellation->cancel(Visitor::of($request)->user(), $id);

        return match ($result->status) {
            CancellationStatus::Cancelled => Response::seeOther(AdminUrls::day(
                $result->booking()->date,
                $result->email() === EmailDelivery::Failed ? AdminNotice::CancelledWithoutEmail : AdminNotice::Cancelled,
                $result->booking()->id,
            )),
            CancellationStatus::AlreadyCancelled => Response::seeOther(AdminUrls::day($result->booking()->date, AdminNotice::AlreadyCancelled, $result->booking()->id)),
            CancellationStatus::Throttled => $this->throttled($request, $id, $result->retryAfter),
            CancellationStatus::NotFound => $this->missing(),
        };
    }

    /** Troppi tentativi: la stessa pagina di conferma con l'avviso, se la prenotazione si disdice ancora. */
    private function throttled(Request $request, int $id, int $retryAfter): Response
    {
        $entry = $this->cancellation->cancellable($id);

        return $entry === null ? $this->missing() : $this->pages->tooManyAttempts('cancel', $this->data($request, $entry), $retryAfter);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, RosterEntry $entry): array
    {
        $booking = $entry->booking;

        return [
            'csrf' => CsrfToken::of($request)->value,
            'entry' => $entry,
            'day' => $this->dates->format($booking->date->calendarDay()),
            'endTime' => $this->calendar->endOf($booking->startsAt)->format('H:i'),
            'capacity' => $this->calendar->rules()->capacity,
            'action' => AdminUrls::cancel($booking->id),
            'backUrl' => AdminUrls::day($booking->date),
            'alert' => null,
        ];
    }

    private function missing(): Response
    {
        return $this->pages->render('cancel-missing', [], 404);
    }
}
