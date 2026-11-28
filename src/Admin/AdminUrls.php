<?php

declare(strict_types=1);

namespace FourStreet\Admin;

use FourStreet\Admin\Http\AdminNotice;
use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\Month;

/**
 * Gli indirizzi del pannello del titolare, in un posto solo: rotte, link delle pagine e redirect.
 * Negli indirizzi non va mai un nome, un'email né una nota del titolare: dopo un redirect l'avviso porta solo
 * numeri (di prenotazioni) e giorni, e la pagina legge il resto dal database.
 */
final class AdminUrls
{
    /** Il giorno: le fasce con chi è prenotato. */
    public const string HOME = '/gestione/';

    /** Una prenotazione nuova in una fascia: il modulo (GET) e la prenotazione (POST). */
    public const string NEW_BOOKING = '/gestione/prenotazioni/nuova/';

    /** La disdetta di una prenotazione: la pagina di conferma (GET) e la disdetta (POST). */
    public const string CANCEL = '/gestione/prenotazioni/{id}/disdici/';

    /** Il mese a colpo d'occhio: ogni giorno con l'orario e le persone prenotate. */
    public const string CALENDAR = '/gestione/calendario/';

    /** La settimana tipo: la pagina (GET) e le sue modifiche (POST). */
    public const string SCHEDULE = '/gestione/orari/';

    /** Una settimana precisa, da lunedì a domenica: la pagina (GET) e le modifiche dei suoi giorni (POST). */
    public const string WEEK = '/gestione/orari/settimana/';

    /** Le chiusure: l'elenco e il modulo (GET), una chiusura nuova o una riapertura (POST). */
    public const string CLOSURES = '/gestione/chiusure/';

    /** Le disdette della palestra con com'è andata l'email: l'elenco (GET) e "Invia le email rimaste" (POST). */
    public const string CANCELLATIONS = '/gestione/disdette/';

    /** Le regole delle prenotazioni che il titolare cambia: il modulo (GET) e il salvataggio (POST). */
    public const string SETTINGS = '/gestione/impostazioni/';

    /** I parametri, gli stessi delle pagine del cliente: il giorno ("2026-12-07"), la fascia ("18:30"), la prenotazione dell'avviso. */
    public const string DAY_PARAMETER = BookingUrls::DAY_PARAMETER;
    public const string TIME_PARAMETER = BookingUrls::TIME_PARAMETER;
    public const string BOOKING_PARAMETER = BookingUrls::BOOKING_PARAMETER;

    /** Il mese del calendario ("2026-12"). */
    public const string MONTH_PARAMETER = 'mese';

    /** Il lunedì della settimana dell'orario ("2026-12-07"). */
    public const string WEEK_PARAMETER = 'da';

    /** Il giorno della settimana dell'orario da tenere aperto dopo una modifica ("2026-12-09"). */
    public const string OPEN_PARAMETER = 'aperto';

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

    /** Il calendario di un mese (senza mese, quello di oggi). */
    public static function calendar(?Month $month = null): string
    {
        return self::with(self::CALENDAR, $month === null ? [] : [self::MONTH_PARAMETER => $month->value]);
    }

    /**
     * La settimana dell'orario che comincia quel lunedì (senza, quella di oggi), con il giorno da tenere aperto.
     *
     * @param array<string, string> $query altri parametri, per esempio quelli dell'avviso dopo una modifica
     */
    public static function week(?LocalDate $monday = null, ?LocalDate $open = null, array $query = []): string
    {
        $parameters = [];
        if ($monday !== null) {
            $parameters[self::WEEK_PARAMETER] = $monday->value;
        }
        if ($open !== null) {
            $parameters[self::OPEN_PARAMETER] = $open->value;
        }

        return self::with(self::WEEK, [...$parameters, ...$query]);
    }

    /**
     * Un indirizzo del pannello con i suoi parametri, nell'ordine dato. Solo valori che si possono mostrare a chiunque:
     * giorni, orari, numeri, avvisi.
     *
     * @param array<string, string> $query
     */
    public static function with(string $path, array $query): string
    {
        $pairs = [];
        foreach ($query as $name => $value) {
            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        return $pairs === [] ? $path : $path . '?' . implode('&', $pairs);
    }
}
