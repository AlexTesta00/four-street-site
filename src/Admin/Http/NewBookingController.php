<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\StaffBookingForm;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\Http\SlotSelection;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\StaffBooking;
use FourStreet\Clock\Clock;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;
use LogicException;

/**
 * /gestione/prenotazioni/nuova/?giorno=…&fascia=…: il titolare aggiunge una persona a una fascia. Con GET il modulo
 * (per chi è il posto: l'email di un cliente registrato oppure il nome di chi non ha l'account), con POST (token CSRF)
 * la prenotazione, poi 303 sul giorno con l'avviso: ricaricare non prenota due volte, e lo stesso modulo inviato
 * di nuovo porta al giorno con la prenotazione che c'è già.
 * Gli esiti: modulo scritto male o email che non è di un cliente (422) e cliente già prenotato quel giorno (409)
 * tornano nel modulo, con l'errore sul campo; fascia piena o iniziata (409), inesistente o parametri manomessi (404)
 * hanno la loro pagina.
 */
final readonly class NewBookingController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private StaffBooking $booking,
        private StaffBlockedPage $blocked,
        private ItalianDateFormatter $dates,
        private Clock $clock,
    ) {}

    public function __invoke(Request $request): Response
    {
        $submitted = $request->method === 'POST';
        $selection = $submitted ? SlotSelection::fromBody($request) : SlotSelection::fromQuery($request);
        if ($selection->tampered || ($submitted && ($selection->date === null || $selection->time === null))) {
            return $this->blocked->render(BookingResult::notFound());
        }
        if ($selection->date === null || $selection->time === null) {
            // Un indirizzo senza la fascia: la fascia si sceglie nel giorno.
            return Response::seeOther(AdminUrls::day($selection->date));
        }
        if (!$submitted) {
            return $this->form($request, $selection->date, $selection->time, StaffBookingForm::blank());
        }

        $form = StaffBookingForm::fromRequest($request);
        if (!$form->isValid()) {
            return $this->form($request, $selection->date, $selection->time, $form, 422);
        }

        return $this->book($request, $selection->date, $selection->time, $form);
    }

    private function book(Request $request, LocalDate $date, SlotTime $time, StaffBookingForm $form): Response
    {
        $staff = Visitor::of($request)->user();
        $email = $form->customerEmail();
        $result = $email === null
            ? $this->booking->bookGuest($staff, $form->guestName() ?? throw new LogicException('Il modulo valido ha l\'email oppure il nome.'), $date, $time)
            : $this->booking->bookCustomer($staff, $email, $date, $time);

        return match (true) {
            $result->status === BookingStatus::Booked => Response::seeOther(AdminUrls::day(
                $date,
                $result->email() === EmailDelivery::Failed ? AdminNotice::BookedWithoutEmail : AdminNotice::Booked,
                $result->booking()->id,
            )),
            $result->isRepeat() => Response::seeOther(AdminUrls::day($date, AdminNotice::AlreadyBooked, $result->booking()->id)),
            // Il cliente ha già un'altra fascia quel giorno: il modulo di nuovo, per scegliere un'altra persona.
            $result->status === BookingStatus::AlreadyBooked => $this->form(
                $request,
                $date,
                $time,
                $form->withBookingThatDay($result->booking()->time->value, $this->stillCancellable($result->booking())),
                409,
            ),
            $result->status === BookingStatus::UnknownCustomer => $this->form($request, $date, $time, $form->withUnknownCustomer(), 422),
            $result->status === BookingStatus::Throttled => $this->form($request, $date, $time, $form, retryAfter: $result->retryAfter),
            default => $this->blocked->render($result),
        };
    }

    /** Il titolare può ancora disdire quella prenotazione: come StaffCancellation, fino all'inizio della fascia. */
    private function stillCancellable(Booking $booking): bool
    {
        return $booking->isActive() && $this->clock->now() < $booking->startsAt;
    }

    /**
     * Il modulo, se la fascia accoglie ancora qualcuno; altrimenti la pagina che dice perché no.
     *
     * @param int|null $retryAfter dopo troppi tentativi: lo stesso modulo con l'avviso, 429
     */
    private function form(Request $request, LocalDate $date, SlotTime $time, StaffBookingForm $form, int $status = 200, ?int $retryAfter = null): Response
    {
        $check = $this->booking->check($date, $time);
        if ($check->status !== BookingStatus::Available) {
            return $retryAfter === null ? $this->blocked->render($check) : $this->blocked->tooManyAttempts($check, $retryAfter);
        }

        $slot = $check->slot();
        $data = [
            'csrf' => CsrfToken::of($request)->value,
            'slot' => $slot,
            'seats' => $check->seats(),
            'day' => $this->dates->format($slot->date->calendarDay(), withYear: false),
            'form' => $form,
            'backUrl' => AdminUrls::day($slot->date),
            'alert' => null,
        ];

        return $retryAfter === null ? $this->pages->render('new-booking', $data, $status) : $this->pages->tooManyAttempts('new-booking', $data, $retryAfter);
    }
}
