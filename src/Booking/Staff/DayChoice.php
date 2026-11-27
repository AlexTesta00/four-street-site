<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;

/**
 * Un giorno nella scelta rapida del pannello, e se ha delle fasce: un giorno senza (domenica, chiusura)
 * non si sceglie, come in /prenota/.
 */
final readonly class DayChoice
{
    public function __construct(
        public LocalDate $date,
        public bool $hasSlots,
    ) {}
}
