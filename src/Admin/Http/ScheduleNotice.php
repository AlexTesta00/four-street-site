<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Booking\GymEmail;
use FourStreet\Booking\Schedule\CancelledBooking;
use FourStreet\Http\Request;

/**
 * Gli avvisi dopo una modifica dell'orario ("?avviso=giorno-chiuso"): il parametro sceglie solo fra questi testi fissi.
 * Se la modifica ha disdetto delle prenotazioni, l'indirizzo dice anche quante ("disdette=3") e porta i numeri di
 * quelle a cui non è arrivata l'email della palestra ("da-avvisare=12,15,40"): le persone senza account e i clienti
 * con l'email non partita o rimasta in attesa. Chi è chi, e com'è l'email adesso, lo legge la pagina dal database
 * (ChangeReport), come nomi ed email; una nota mai.
 */
enum ScheduleNotice: string
{
    public const string PARAMETER = AdminNotice::PARAMETER;

    /** Quante prenotazioni ha disdetto la modifica. */
    public const string CANCELLED = 'disdette';

    /** I numeri delle prenotazioni disdette a cui non è arrivata l'email della palestra. */
    public const string NOT_EMAILED = 'da-avvisare';

    /** Al più tanti numeri nell'elenco: l'indirizzo resta corto, e oltre il conto basta (la pagina Disdette ha tutto). */
    public const int MAX_LISTED = 50;

    case TimeAdded = 'orario-aggiunto';
    case TimeRemoved = 'orario-tolto';
    case DayClosed = 'giorno-chiuso';
    case DayReopened = 'giorno-riaperto';
    case BackToWeek = 'tornato-alla-settimana-tipo';
    case WeekCopied = 'settimana-copiata';
    case ClosureSaved = 'chiusura-salvata';
    case ClosureRemoved = 'chiusura-tolta';
    case NothingToChange = 'nulla-da-cambiare';

    public static function fromRequest(Request $request): ?self
    {
        return self::tryFrom($request->queryParam(self::PARAMETER) ?? '');
    }

    public function title(): string
    {
        return match ($this) {
            self::TimeAdded => 'Orario aggiunto',
            self::TimeRemoved => 'Orario tolto',
            self::DayClosed => 'Giorno chiuso',
            self::DayReopened => 'Giorno riaperto',
            self::BackToWeek => 'Tornato alla settimana tipo',
            self::WeekCopied => 'Settimana copiata',
            self::ClosureSaved => 'Chiusura salvata',
            self::ClosureRemoved => 'Chiusura tolta',
            self::NothingToChange => 'Nulla da cambiare',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::TimeAdded => 'I clienti trovano la fascia nuova fra quelle da prenotare.',
            self::TimeRemoved => 'La fascia non c\'è più: i clienti non la trovano fra quelle da prenotare.',
            self::DayClosed => 'Il giorno non ha più fasce: nessuno lo prenota.',
            self::DayReopened => 'Il giorno segue di nuovo la settimana tipo.',
            self::BackToWeek => 'Il giorno segue di nuovo la settimana tipo, con i suoi orari.',
            self::WeekCopied => 'Questa settimana ha adesso gli stessi orari e le stesse chiusure della settimana prima.',
            self::ClosureSaved => 'Nei giorni chiusi non ci sono fasce: nessuno prenota.',
            self::ClosureRemoved => 'Si torna agli orari della settimana tipo.',
            self::NothingToChange => 'L\'orario era già così: non è cambiato nulla.',
        };
    }

    /** Un'informazione (nulla è cambiato), non una conferma. */
    public function isInformation(): bool
    {
        return $this === self::NothingToChange;
    }

    /**
     * I parametri dell'indirizzo dopo la modifica: l'avviso e, se ha disdetto qualcosa, quante prenotazioni e i numeri
     * di quelle a cui l'email della palestra non è arrivata (al più MAX_LISTED): senza account, non partita, in attesa.
     *
     * @param list<CancelledBooking> $cancelled
     *
     * @return array<string, string>
     */
    public function query(array $cancelled = []): array
    {
        $query = [self::PARAMETER => $this->value];
        if ($cancelled === []) {
            return $query;
        }

        $query[self::CANCELLED] = (string) count($cancelled);
        $ids = [];
        foreach ($cancelled as $booking) {
            if ($booking->email !== GymEmail::Sent && count($ids) < self::MAX_LISTED) {
                $ids[] = $booking->entry->booking->id;
            }
        }
        if ($ids !== []) {
            $query[self::NOT_EMAILED] = implode(',', $ids);
        }

        return $query;
    }
}
