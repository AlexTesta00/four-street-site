<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;

/**
 * Una chiusura nell'elenco del pannello: uno o più giorni di fila chiusi, con la stessa nota del titolare (a uso interno).
 */
final readonly class ClosureRange
{
    public function __construct(
        public LocalDate $from,
        public LocalDate $to,
        public ?string $note,
    ) {}

    public function isSingleDay(): bool
    {
        return $this->from->equals($this->to);
    }

    /** Lo stesso intervallo con un giorno in più alla fine. */
    public function through(LocalDate $to): self
    {
        return new self($this->from, $to, $this->note);
    }
}
