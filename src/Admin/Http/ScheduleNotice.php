<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\Schedule\CancelledBooking;
use FourStreet\Http\Request;

/**
 * Gli avvisi dopo una modifica dell'orario ("?avviso=giorno-chiuso"): il parametro sceglie solo fra questi testi fissi.
 * Se la modifica ha disdetto delle prenotazioni, l'indirizzo dice anche quante ("disdette=3") e porta i numeri di
 * quelle che il titolare deve avvisare a mano: le persone senza account ("senza-account=12,15") e i clienti a cui
 * l'email non è partita ("senza-email=40"). Nomi, email e note mai: li legge la pagina dal database (ChangeReport).
 */
enum ScheduleNotice: string
{
    public const string PARAMETER = AdminNotice::PARAMETER;

    /** Quante prenotazioni ha disdetto la modifica. */
    public const string CANCELLED = 'disdette';

    /** I numeri delle prenotazioni disdette di persone senza account: nessuna email, le avvisa il titolare. */
    public const string WITHOUT_ACCOUNT = 'senza-account';

    /** I numeri delle prenotazioni disdette di clienti a cui l'email non è partita. */
    public const string WITHOUT_EMAIL = 'senza-email';

    /** Al più tanti numeri per elenco: l'indirizzo resta corto, e oltre il conto basta. */
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
     * di quelle da avvisare a mano (al più MAX_LISTED per elenco).
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
        foreach ([self::WITHOUT_ACCOUNT => EmailDelivery::NoAddress, self::WITHOUT_EMAIL => EmailDelivery::Failed] as $parameter => $delivery) {
            $ids = [];
            foreach ($cancelled as $booking) {
                if ($booking->email === $delivery && count($ids) < self::MAX_LISTED) {
                    $ids[] = $booking->entry->booking->id;
                }
            }
            if ($ids !== []) {
                $query[$parameter] = implode(',', $ids);
            }
        }

        return $query;
    }
}
