<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\AccountUrls;
use FourStreet\Booking\Http\BookingNotice;

/**
 * Gli indirizzi delle prenotazioni, in un posto solo: rotte, link delle pagine, delle email e redirect.
 */
final class BookingUrls
{
    /** La scelta del giorno e della fascia. */
    public const string DAY = '/prenota/';

    /** Il riepilogo della fascia scelta (GET) e la conferma (POST). */
    public const string CONFIRM = '/prenota/conferma/';

    /** La disdetta di una prenotazione: la pagina di conferma (GET) e la disdetta (POST). */
    public const string CANCEL = '/account/prenotazioni/{id}/disdici/';

    /** I parametri: il giorno ("2026-12-07") e l'orario della fascia ("18:30"). */
    public const string DAY_PARAMETER = 'giorno';
    public const string TIME_PARAMETER = 'fascia';

    /** Il parametro della pagina dell'account con la prenotazione dell'avviso. */
    public const string BOOKING_PARAMETER = 'prenotazione';

    /** La pagina delle fasce, su un giorno se indicato. */
    public static function day(?LocalDate $day = null): string
    {
        return $day === null ? self::DAY : self::DAY . '?' . self::DAY_PARAMETER . '=' . $day->value;
    }

    /** Il riepilogo di una fascia, prima della conferma. */
    public static function confirm(LocalDate $day, SlotTime $time): string
    {
        return self::CONFIRM . '?' . self::DAY_PARAMETER . '=' . $day->value . '&' . self::TIME_PARAMETER . '=' . rawurlencode($time->value);
    }

    public static function cancel(int $bookingId): string
    {
        return str_replace('{id}', (string) $bookingId, self::CANCEL);
    }

    /** Il numero di una prenotazione scritto in un indirizzo ("42"), oppure null se non lo è ("042", "4a", "-1"). */
    public static function bookingId(?string $value): ?int
    {
        return $value !== null && preg_match('/\A[1-9]\d{0,17}\z/', $value) === 1 ? (int) $value : null;
    }

    /** La pagina dell'account con un avviso sulle prenotazioni (e la prenotazione di cui parla). */
    public static function account(BookingNotice $notice, ?int $bookingId = null): string
    {
        $query = BookingNotice::PARAMETER . '=' . $notice->value;
        if ($bookingId !== null) {
            $query .= '&' . self::BOOKING_PARAMETER . '=' . $bookingId;
        }

        return AccountUrls::HOME . '?' . $query;
    }
}
