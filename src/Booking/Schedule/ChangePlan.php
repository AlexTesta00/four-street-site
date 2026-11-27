<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\RosterEntry;
use LogicException;

/**
 * Come andrebbe una modifica dell'orario adesso, senza farla: perché non si può (refusal), oppure quali giorni
 * cambiano e chi è prenotato dove una fascia sparisce. Quelle prenotazioni la modifica le disdice: prima il titolare
 * le vede nella pagina di conferma, e conferma proprio quelle (fingerprint()).
 */
final readonly class ChangePlan
{
    /**
     * @param list<DayWrite> $days i giorni che cambiano, dal primo
     * @param list<RosterEntry> $affected le prenotazioni attive non ancora iniziate dove una fascia sparisce, per giorno, fascia e posto
     */
    public function __construct(
        public ScheduleChange $change,
        public ?ScheduleRefusal $refusal = null,
        public array $days = [],
        public ?WeekdayWrite $weekday = null,
        public array $affected = [],
    ) {}

    public static function refused(ScheduleChange $change, ScheduleRefusal $refusal): self
    {
        return new self($change, $refusal);
    }

    public function isRefused(): bool
    {
        return $this->refusal !== null;
    }

    /** La modifica non cambierebbe nulla: l'orario è già così. */
    public function changesNothing(): bool
    {
        return $this->refusal === null && $this->days === [] && $this->weekday === null;
    }

    public function refusal(): ScheduleRefusal
    {
        return $this->refusal ?? throw new LogicException('La modifica si può fare.');
    }

    /** Le prenotazioni da disdire, come le ha viste il titolare: i loro numeri in ordine ("12,15,40"); vuoto se nessuna. */
    public function fingerprint(): string
    {
        return self::fingerprintOf($this->affected);
    }

    /**
     * @param list<RosterEntry> $entries
     */
    public static function fingerprintOf(array $entries): string
    {
        $ids = array_map(static fn(RosterEntry $entry): int => $entry->booking->id, $entries);
        sort($ids);

        return implode(',', $ids);
    }

    /** Il giorno chiude: le sue prenotazioni si disdicono perché la palestra è chiusa, non per una fascia tolta. */
    public function closes(RosterEntry $entry): bool
    {
        foreach ($this->days as $day) {
            if ($day->date->equals($entry->booking->date)) {
                return $day->kind === DayKind::Closed;
            }
        }

        return false;
    }
}
