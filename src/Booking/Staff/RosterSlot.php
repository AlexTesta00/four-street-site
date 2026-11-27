<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\Slot;

/**
 * Una fascia nel pannello del titolare: chi è prenotato, dal posto 1, e cosa si può fare adesso.
 * Dopo una capienza abbassata le prenotazioni possono essere più dei posti: si vedono tutte, e la fascia è piena.
 */
final readonly class RosterSlot
{
    /**
     * @param positive-int $capacity i posti della fascia
     * @param list<RosterEntry> $entries le prenotazioni attive, dal posto 1
     * @param bool $scheduled la fascia è nell'orario di adesso; se l'orario è cambiato dopo le prenotazioni,
     *                        restano da vedere (e da disdire) le sue prenotazioni, ma non si aggiunge nessuno
     */
    public function __construct(
        public ScheduledSlot $slot,
        public int $capacity,
        public array $entries,
        public SlotPhase $phase,
        public bool $scheduled = true,
    ) {}

    public function taken(): int
    {
        return count($this->entries);
    }

    public function isFull(): bool
    {
        return $this->taken() >= $this->capacity;
    }

    /** Il titolare può aggiungere una persona: la fascia è nell'orario, non è iniziata e ha un posto libero. */
    public function canAdd(): bool
    {
        return $this->scheduled && $this->phase === SlotPhase::Upcoming && !$this->isFull();
    }

    /** Le sue prenotazioni si disdicono: la fascia non è ancora iniziata. */
    public function canCancel(): bool
    {
        return $this->phase === SlotPhase::Upcoming;
    }

    /** I posti presi, per i quadrati di partials/booking/seats (nessuno segnato come il prossimo). */
    public function seats(): Slot
    {
        return new Slot(
            $this->slot->time->value,
            $this->capacity,
            array_map(static fn(RosterEntry $entry): int => $entry->booking->seat(), $this->entries),
            closed: true,
        );
    }
}
