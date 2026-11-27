<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Account\Notice;
use FourStreet\Http\Request;
use FourStreet\View\Alert;

/**
 * Gli avvisi delle prenotazioni sulla pagina dell'account, dopo un redirect ("?avviso=prenotazione-confermata").
 * Stesso parametro degli avvisi dell'area clienti, valori diversi: ognuno legge solo i suoi.
 * Il parametro sceglie solo fra questi testi fissi; il giorno e l'orario li legge la pagina dal database.
 */
enum BookingNotice: string
{
    public const string PARAMETER = Notice::PARAMETER;

    case Booked = 'prenotazione-confermata';
    case BookedWithoutEmail = 'prenotazione-confermata-senza-email';
    case AlreadyYours = 'prenotazione-gia-tua';
    case Cancelled = 'prenotazione-disdetta';
    case CancelledWithoutEmail = 'prenotazione-disdetta-senza-email';
    case AlreadyCancelled = 'prenotazione-gia-disdetta';

    public static function fromRequest(Request $request): ?self
    {
        return self::tryFrom($request->queryParam(self::PARAMETER) ?? '');
    }

    /**
     * @param string|null $slot la fascia di cui parla, a parole ("Lunedì 7 dicembre alle 18:30"), se si conosce
     */
    public function alert(?string $slot): Alert
    {
        $lead = $slot === null ? '' : $slot . '. ';

        return match ($this) {
            self::Booked => Alert::success($lead . 'Ti abbiamo mandato un\'email con i dettagli e l\'evento da aggiungere al calendario.', 'Prenotazione confermata'),
            self::BookedWithoutEmail => Alert::success($lead . 'L\'email di conferma non è partita, ma la prenotazione vale: la trovi qui sotto.', 'Prenotazione confermata'),
            self::AlreadyYours => Alert::info($lead . 'Questa fascia è già prenotata a tuo nome: la trovi qui sotto.', 'Già prenotata'),
            self::Cancelled => Alert::success($lead . 'Il posto è tornato libero. Ti abbiamo mandato un\'email di conferma.', 'Prenotazione disdetta'),
            self::CancelledWithoutEmail => Alert::success($lead . 'Il posto è tornato libero. L\'email di conferma della disdetta non è partita.', 'Prenotazione disdetta'),
            self::AlreadyCancelled => Alert::info($lead . 'Questa prenotazione è già disdetta: il posto è tornato libero.', 'Già disdetta'),
        };
    }
}
