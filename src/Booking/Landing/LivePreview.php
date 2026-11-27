<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotCatalog;

/**
 * Le fasce vere di un giorno, con i posti presi: il primo giorno prenotabile con una fascia libera
 * (oppure, se sono tutte piene, il primo con delle fasce). Senza giorno né fasce se nei prossimi
 * giorni non si prenota nulla.
 */
final readonly class LivePreview implements SlotCatalog, SlotPreview
{
    /**
     * @param positive-int $capacity
     * @param string $dayLabel il giorno a parole: "lunedì 7 dicembre"
     * @param list<Slot> $slots
     */
    public function __construct(
        private int $capacity,
        public ?LocalDate $day,
        public string $dayLabel,
        private array $slots,
    ) {}

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function slots(): array
    {
        return $this->slots;
    }

    /** La prima fascia che si può prenotare, oppure null se sono tutte piene o chiuse. */
    public function defaultSlot(): ?Slot
    {
        foreach ($this->slots as $slot) {
            if ($slot->isBookable()) {
                return $slot;
            }
        }

        return null;
    }
}
