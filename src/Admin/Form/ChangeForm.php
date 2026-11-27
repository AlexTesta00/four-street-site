<?php

declare(strict_types=1);

namespace FourStreet\Admin\Form;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Http\Request;

/**
 * Uno dei moduli piccoli delle pagine dell'orario, così come l'ha inviato il titolare: l'azione e i suoi campi.
 * Alcuni li scrive la pagina, nascosti (il giorno, l'orario di un chip, il lunedì da copiare, l'intervallo da
 * riaprire): si leggono solo nella forma esatta in cui la pagina li scrive, e uno scritto male vuol dire un modulo
 * manomesso. Gli altri li scrive il titolare (l'orario da aggiungere, la nota di una chiusura): li controllano
 * TimeField e ClosureNote, che dicono cosa correggere. Tutti i valori restano come sono arrivati, per ricompilare
 * il modulo se c'è un errore.
 */
final readonly class ChangeForm
{
    public const string ACTION = 'azione';

    /** Il giorno della settimana tipo: "1" (lunedì) … "7" (domenica), come Weekday. */
    public const string WEEKDAY = 'giorno-settimana';

    /** Il giorno di una settimana precisa, "2026-12-09". */
    public const string DAY = 'giorno';

    public const string TIME = 'orario';

    public const string NOTE = 'nota';

    /** Il primo giorno: il lunedì della settimana da copiare, oppure il primo giorno di una chiusura. */
    public const string FROM = 'da';

    /** L'ultimo giorno di una chiusura. */
    public const string TO = 'a';

    /** Le prenotazioni che il titolare ha visto nella pagina di conferma: ChangePlan::fingerprint(), "12,15,40". */
    public const string CONFIRMED = 'prenotazioni';

    private function __construct(
        public ?ChangeAction $action,
        public string $weekday,
        public string $day,
        public string $time,
        public string $note,
        public string $from,
        public string $to,
        public string $confirmed,
    ) {}

    /** Nessun modulo inviato: le pagine aperte con GET. */
    public static function none(): self
    {
        return new self(null, '', '', '', '', '', '', '');
    }

    public static function fromRequest(Request $request): self
    {
        $field = static fn(string $name): string => $request->bodyParam($name) ?? '';

        return new self(
            ChangeAction::tryFrom($field(self::ACTION)),
            $field(self::WEEKDAY),
            $field(self::DAY),
            $field(self::TIME),
            $field(self::NOTE),
            $field(self::FROM),
            $field(self::TO),
            $field(self::CONFIRMED),
        );
    }

    /** Il giorno della settimana tipo, scritto come lo scrive la pagina: una cifra da 1 a 7. */
    public function weekday(): ?Weekday
    {
        return self::weekdayFrom($this->weekday);
    }

    /** Il giorno di una settimana precisa, scritto come lo scrive la pagina (AAAA-MM-GG). */
    public function day(): ?LocalDate
    {
        return LocalDate::tryFrom($this->day);
    }

    /** L'orario di un chip, nascosto: "18:30", come lo scrive la pagina (quello da aggiungere lo legge TimeField). */
    public function chipTime(): ?SlotTime
    {
        return SlotTime::tryFrom($this->time);
    }

    /** Il primo giorno, nascosto (il lunedì da copiare, il primo giorno da riaprire): AAAA-MM-GG. */
    public function from(): ?LocalDate
    {
        return LocalDate::tryFrom($this->from);
    }

    /** L'ultimo giorno da riaprire, nascosto: AAAA-MM-GG. */
    public function to(): ?LocalDate
    {
        return LocalDate::tryFrom($this->to);
    }

    /**
     * Il modulo inviato è quello di quell'azione, su quel giorno ("1" per il lunedì della settimana tipo, oppure
     * "2026-12-09"): la pagina lo ricompila con i valori scritti.
     */
    public function isFor(ChangeAction $action, string $target): bool
    {
        return $this->action === $action && ($this->weekday === $target || $this->day === $target);
    }

    /** Un giorno della settimana scritto come una cifra da 1 (lunedì) a 7 (domenica); null altrimenti. */
    public static function weekdayFrom(?string $value): ?Weekday
    {
        return $value !== null && preg_match('/\A[1-7]\z/', $value) === 1 ? Weekday::from((int) $value) : null;
    }
}
