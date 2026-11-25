<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use InvalidArgumentException;

/**
 * Una fascia oraria: l'orario d'inizio, i posti e quanti sono già presi.
 */
final readonly class Slot
{
    /** Orario a 24 ore con due cifre: "07:00", "18:30". */
    public const string TIME_PATTERN = '/\A(?:[01]\d|2[0-3]):[0-5]\d\z/';

    public function __construct(
        public string $time,
        public int $capacity,
        public int $taken,
    ) {
        if (preg_match(self::TIME_PATTERN, $time) !== 1) {
            throw new InvalidArgumentException(sprintf('Orario di fascia non valido: "%s".', $time));
        }
        if ($capacity < 1) {
            throw new InvalidArgumentException(sprintf('Una fascia ha almeno un posto: %d.', $capacity));
        }
        if ($taken < 0 || $taken > $capacity) {
            throw new InvalidArgumentException(sprintf('Posti occupati fuori dai limiti: %d su %d.', $taken, $capacity));
        }
    }

    public function isFull(): bool
    {
        return $this->taken === $this->capacity;
    }

    public function freeSeats(): int
    {
        return $this->capacity - $this->taken;
    }

    /**
     * Il posto che prenderebbe chi prenota adesso (1 per il primo), oppure null se la fascia è piena.
     */
    public function nextSeat(): ?int
    {
        return $this->isFull() ? null : $this->taken + 1;
    }
}
