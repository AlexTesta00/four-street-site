<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Account\Http\DashboardSection;
use FourStreet\Account\User;
use FourStreet\Booking\BookingAgenda;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\BookingUrls;
use FourStreet\Error\ExceptionReporter;
use FourStreet\Http\Request;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Html;
use FourStreet\View\View;
use Throwable;

/**
 * "Le tue prenotazioni" nella pagina dell'account: le prossime (giorno, orario, posto) con "Disdici",
 * l'avviso dopo una prenotazione o una disdetta e, se le prenotazioni sono attive, il link per prenotare.
 * Un errore delle prenotazioni (per esempio la migrazione non ancora lanciata) non porta giù l'account:
 * si registra e la sezione dice che per ora non si possono mostrare.
 */
final readonly class BookingsSection implements DashboardSection
{
    public function __construct(
        private View $view,
        private BookingAgenda $agenda,
        private BookingRules $rules,
        private ItalianDateFormatter $dates,
        private ExceptionReporter $reporter,
    ) {}

    public function render(Request $request, User $customer): Html
    {
        try {
            $entries = $this->agenda->upcoming($customer);
            $alert = BookingNotice::fromRequest($request)?->alert($this->noticeSlot($request, $customer));
        } catch (Throwable $error) {
            $this->reporter->report($error);

            return $this->view->render('partials/account/bookings', [...$this->common(), 'entries' => [], 'alert' => null, 'unavailable' => true, 'bookUrl' => null]);
        }

        return $this->view->render('partials/account/bookings', [...$this->common(), 'entries' => $entries, 'alert' => $alert, 'unavailable' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function common(): array
    {
        return [
            'capacity' => $this->rules->capacity,
            'bookUrl' => $this->rules->online ? BookingUrls::DAY : null,
            'dates' => $this->dates,
        ];
    }

    /** La fascia di cui parla l'avviso, a parole ("Lunedì 7 dicembre alle 18:30"), se è una prenotazione del cliente. */
    private function noticeSlot(Request $request, User $customer): ?string
    {
        $id = BookingUrls::bookingId($request->queryParam(BookingUrls::BOOKING_PARAMETER));
        $booking = $id === null ? null : $this->agenda->find($customer, $id);

        return $booking === null ? null : $this->dates->format($booking->date->calendarDay(), withYear: false) . ' alle ' . $booking->time->value;
    }
}
