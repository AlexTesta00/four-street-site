<?php

declare(strict_types=1);

namespace FourStreet\Admin;

use FourStreet\Admin\Http\AdminNotice;
use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;

/**
 * Gli indirizzi del pannello del titolare, in un posto solo: rotte, link delle pagine e redirect.
 * Negli indirizzi non va mai un nome né un'email: dopo un redirect l'avviso porta solo il numero della
 * prenotazione, e la pagina legge il resto dal database.
 */
final class AdminUrls
{
    /** Il giorno: le fasce con chi è prenotato. */
    public const string HOME = '/gestione/';

    /** Una prenotazione nuova in una fascia: il modulo (GET) e la prenotazione (POST). */
    public const string NEW_BOOKING = '/gestione/prenotazioni/nuova/';

    /** La disdetta di una prenotazione: la pagina di conferma (GET) e la disdetta (POST). */
    public const string CANCEL = '/gestione/prenotazioni/{id}/disdici/';

    /** I parametri, gli stessi delle pagine del cliente: il giorno ("2026-12-07"), la fascia ("18:30"), la prenotazione dell'avviso. */
    public const string DAY_PARAMETER = BookingUrls::DAY_PARAMETER;
    public const string TIME_PARAMETER = BookingUrls::TIME_PARAMETER;
    public const string BOOKING_PARAMETER = BookingUrls::BOOKING_PARAMETER;

    /** Il giorno nel pannello (senza giorno, quello predefinito), con un avviso e la prenotazione di cui parla. */
    public static function day(?LocalDate $day = null, ?AdminNotice $notice = null, ?int $bookingId = null): string
    {
        $query = [];
        if ($day !== null) {
            $query[] = self::DAY_PARAMETER . '=' . $day->value;
        }
        if ($notice !== null) {
            $query[] = AdminNotice::PARAMETER . '=' . $notice->value;
        }
        if ($bookingId !== null) {
            $query[] = self::BOOKING_PARAMETER . '=' . $bookingId;
        }

        return $query === [] ? self::HOME : self::HOME . '?' . implode('&', $query);
    }

    /** Il modulo per aggiungere una persona a una fascia. */
    public static function newBooking(LocalDate $day, SlotTime $time): string
    {
        return self::NEW_BOOKING . '?' . self::DAY_PARAMETER . '=' . $day->value . '&' . self::TIME_PARAMETER . '=' . rawurlencode($time->value);
    }

    public static function cancel(int $bookingId): string
    {
        return str_replace('{id}', (string) $bookingId, self::CANCEL);
    }
}
