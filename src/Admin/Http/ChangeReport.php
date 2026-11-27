<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Http\Request;

/**
 * L'avviso dopo una modifica dell'orario, letto dall'indirizzo (ScheduleNotice): che cosa è cambiato, quante
 * prenotazioni ha disdetto e, per nome, chi va avvisato a mano. L'indirizzo porta solo numeri: le persone le legge
 * ScheduleBoard::cancelled(), che lascia passare solo prenotazioni disdette dalla palestra. Un numero manomesso, di
 * una prenotazione attiva o disdetta dal cliente, non mostra nessuno; un conto scritto male non conta. Mai un errore.
 */
final readonly class ChangeReport
{
    /** Il conto delle disdette, scritto come lo scrive la pagina: un numero intero positivo, non enorme. */
    private const string COUNT_PATTERN = '/\A[1-9]\d{0,5}\z/';

    /**
     * @param int $cancelled le prenotazioni disdette dalla modifica, tutte
     * @param list<RosterEntry> $withoutAccount le persone senza account: nessuna email, le avvisa il titolare
     * @param list<RosterEntry> $withoutEmail i clienti a cui l'email della palestra non è partita
     * @param bool $partial un elenco è arrivato al massimo dei numeri (ScheduleNotice::MAX_LISTED): forse non c'è tutto
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

        $guestIds = self::ids($request->queryParam(ScheduleNotice::WITHOUT_ACCOUNT));
        $failedIds = self::ids($request->queryParam(ScheduleNotice::WITHOUT_EMAIL));
        $people = $board->cancelled([...$guestIds, ...$failedIds]);
        // Ognuno nel suo elenco: senza account solo chi non ce l'ha, senza email solo chi un'email ce l'ha.
        $withoutAccount = array_values(array_filter($people, static fn(RosterEntry $entry): bool => in_array($entry->booking->id, $guestIds, true) && $entry->booking->isGuest()));
        $withoutEmail = array_values(array_filter($people, static fn(RosterEntry $entry): bool => in_array($entry->booking->id, $failedIds, true) && $entry->email !== null));

        $count = $request->queryParam(ScheduleNotice::CANCELLED) ?? '';
        $cancelled = preg_match(self::COUNT_PATTERN, $count) === 1 ? (int) $count : 0;

        return new self(
            $notice,
            max($cancelled, count($withoutAccount) + count($withoutEmail)),
            $withoutAccount,
            $withoutEmail,
            count($guestIds) >= ScheduleNotice::MAX_LISTED || count($failedIds) >= ScheduleNotice::MAX_LISTED,
        );
    }

    /** I clienti a cui è arrivata l'email della palestra: tutte le disdette, meno quelle da avvisare a mano. */
    public function emailed(): int
    {
        return $this->cancelled - count($this->withoutAccount) - count($this->withoutEmail);
    }

    /** Qualcuno da avvisare a mano. */
    public function hasPeopleToWarn(): bool
    {
        return $this->withoutAccount !== [] || $this->withoutEmail !== [];
    }

    /**
     * I numeri di un elenco dell'indirizzo ("12,15,40"): solo quelli scritti bene, senza doppioni, al più MAX_LISTED.
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
