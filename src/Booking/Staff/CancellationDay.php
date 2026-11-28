<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;

/**
 * Un giorno della pagina Disdette: le prenotazioni di quel giorno disdette dalla palestra, per fascia, con le loro persone.
 */
final readonly class CancellationDay
{
    /**
     * @param non-empty-list<RosterEntry> $entries per fascia e numero
     */
    public function __construct(
        public LocalDate $date,
        public array $entries,
    ) {}
}
