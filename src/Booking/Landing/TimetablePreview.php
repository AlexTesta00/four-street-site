<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;

/**
 * Gli orari delle fasce di un giorno, senza i posti: la landing quando il database non risponde.
 * Il link porta alla pagina delle prenotazioni, dove i posti si vedono.
 */
final readonly class TimetablePreview implements SlotPreview
{
    /**
     * @param positive-int $capacity
     * @param string $dayLabel il giorno a parole: "lunedì 7 dicembre"
     * @param list<SlotTime> $times gli orari che si prenotano ancora
     */
    public function __construct(
        private int $capacity,
        public ?LocalDate $day,
        public string $dayLabel,
        public array $times,
    ) {}

    public function capacity(): int
    {
        return $this->capacity;
    }
}
