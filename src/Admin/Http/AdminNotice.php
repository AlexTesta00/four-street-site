<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Notice;
use FourStreet\Booking\RosterEntry;
use FourStreet\Http\Request;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;

/**
 * Gli avvisi del pannello dopo un redirect ("?avviso=prenotazione-inserita&prenotazione=12"). Il parametro sceglie
 * solo fra questi testi fissi; chi è e quando lo legge la pagina dal database, dal numero della prenotazione:
 * nell'indirizzo non va mai un nome.
 */
enum AdminNotice: string
{
    public const string PARAMETER = Notice::PARAMETER;

    case Booked = 'prenotazione-inserita';
    case BookedWithoutEmail = 'prenotazione-inserita-senza-email';
    case AlreadyBooked = 'prenotazione-gia-inserita';
    case Cancelled = 'prenotazione-disdetta';
    case CancelledWithoutEmail = 'prenotazione-disdetta-senza-email';
    case AlreadyCancelled = 'prenotazione-gia-disdetta';

    public static function fromRequest(Request $request): ?self
    {
        return self::tryFrom($request->queryParam(self::PARAMETER) ?? '');
    }

    /**
     * @param RosterEntry|null $entry la prenotazione di cui parla, se si trova
     */
    public function alert(?RosterEntry $entry, ItalianDateFormatter $dates): Alert
    {
        $lead = $entry === null ? '' : sprintf(
            '%s, %s alle %s. ',
            $entry->name,
            $dates->format($entry->booking->date->calendarDay(), withYear: false, capitalize: false),
            $entry->booking->time->value,
        );
        $guest = $entry?->booking->isGuest() ?? false;

        return match ($this) {
            self::Booked => Alert::success($lead . ($guest
                ? 'Non ha l\'account: la prenotazione la vedi solo qui.'
                : 'Gli abbiamo mandato l\'email di conferma, e la trova nel suo account.'), 'Prenotazione inserita'),
            self::BookedWithoutEmail => Alert::success($lead . 'L\'email di conferma non è partita: avvisalo tu. La prenotazione vale.', 'Prenotazione inserita'),
            self::AlreadyBooked => Alert::info($lead . ($guest
                ? 'Era già in questa fascia: non è cambiato nulla. Se è un\'altra persona con lo stesso nome, aggiungi l\'iniziale del cognome.'
                : 'Era già in questa fascia: non è cambiato nulla.'), 'Già inserita'),
            self::Cancelled => Alert::success($lead . ($guest
                ? 'Il posto è tornato libero. Non ha l\'account: avvisalo tu.'
                : 'Il posto è tornato libero. Gli abbiamo mandato un\'email per avvisarlo.'), 'Prenotazione disdetta'),
            self::CancelledWithoutEmail => Alert::success($lead . 'Il posto è tornato libero. L\'email di avviso non è partita: avvisalo tu.', 'Prenotazione disdetta'),
            self::AlreadyCancelled => Alert::info($lead . 'Questa prenotazione era già disdetta: il posto è libero.', 'Già disdetta'),
        };
    }
}
