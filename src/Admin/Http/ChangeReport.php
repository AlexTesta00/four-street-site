<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Http\Request;

/**
 * L'avviso dopo una modifica dell'orario, letto dall'indirizzo (ScheduleNotice): che cosa è cambiato, quante
 * prenotazioni ha disdetto e, per nome, chi non ha ricevuto l'email della palestra. L'indirizzo porta solo i numeri:
 * le persone, e com'è l'email adesso, le legge ScheduleBoard::cancelled(), che lascia passare solo prenotazioni
 * disdette dalla palestra. Chi non ha l'account e chi aspetta ancora l'email lo dice il database, non l'indirizzo: un
 * numero manomesso, di una prenotazione attiva o disdetta dal cliente, non mostra nessuno, e un'email partita nel
 * frattempo (dalla pagina Disdette) non compare più. Un conto scritto male non conta. Mai un errore.
 */
final readonly class ChangeReport
{
    /** Il conto delle disdette, scritto come lo scrive la pagina: un numero intero positivo, non enorme. */
    private const string COUNT_PATTERN = '/\A[1-9]\d{0,5}\z/';

    /**
     * @param int $cancelled le prenotazioni disdette dalla modifica, tutte
     * @param list<RosterEntry> $withoutAccount le persone senza account: nessuna email, le avvisa il titolare
     * @param list<RosterEntry> $withoutEmail i clienti a cui l'email della palestra non è partita, o non ancora: com'è
     *                                        adesso (Booking::$gymEmail: in attesa, in invio, non partita)
     * @param bool $partial l'elenco è arrivato al massimo dei numeri (ScheduleNotice::MAX_LISTED): forse non c'è tutto
     */
    public function __construct(
        public ScheduleNotice $notice,
        public int $cancelled,
        public array $withoutAccount,
        public array $withoutEmail,
        public bool $partial,
    ) {}

    /** L'avviso dell'indirizzo, se c'è. */
    public static function fromRequest(Request $request, ScheduleBoard $board): ?self
    {
        $notice = ScheduleNotice::fromRequest($request);
        if ($notice === null) {
            return null;
        }
        // "Nulla da cambiare" non ha mai disdetto nulla: un conto lì è manomesso.
        if ($notice->isInformation()) {
            return new self($notice, 0, [], [], false);
        }

        $ids = self::ids($request->queryParam(ScheduleNotice::NOT_EMAILED));
        $withoutAccount = [];
        $withoutEmail = [];
        foreach ($board->cancelled($ids) as $entry) {
            $email = $entry->booking->gymEmail;
            if ($entry->booking->isGuest()) {
                $withoutAccount[] = $entry;
            } elseif ($email !== null && $email !== GymEmail::Sent) {
                $withoutEmail[] = $entry;
            }
        }

        $count = $request->queryParam(ScheduleNotice::CANCELLED) ?? '';
        $cancelled = preg_match(self::COUNT_PATTERN, $count) === 1 ? (int) $count : 0;

        return new self(
            $notice,
            max($cancelled, count($withoutAccount) + count($withoutEmail)),
            $withoutAccount,
            $withoutEmail,
            count($ids) >= ScheduleNotice::MAX_LISTED,
        );
    }

    /** I clienti a cui è arrivata l'email della palestra: tutte le disdette, meno quelle da avvisare. */
    public function emailed(): int
    {
        return $this->cancelled - count($this->withoutAccount) - count($this->withoutEmail);
    }

    /** Qualche email si manda ancora dal pannello (in attesa, non partita): l'avviso porta alla pagina Disdette. */
    public function hasEmailsToSend(): bool
    {
        foreach ($this->withoutEmail as $entry) {
            if ($entry->booking->gymEmail?->canBeSent() ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * I numeri dell'elenco dell'indirizzo ("12,15,40"): solo quelli scritti bene, senza doppioni, al più MAX_LISTED.
     *
     * @return list<int>
     */
    private static function ids(?string $value): array
    {
        $ids = [];
        foreach (explode(',', $value ?? '') as $part) {
            $id = BookingUrls::bookingId($part);
            if ($id !== null && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (count($ids) >= ScheduleNotice::MAX_LISTED) {
                break;
            }
        }

        return $ids;
    }
}
