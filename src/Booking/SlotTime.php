<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use Stringable;

/**
 * L'orario d'inizio di una fascia, ora locale del sito, a 24 ore con due cifre: "07:00", "18:30".
 * È un'ora del giorno, senza data né fuso: diventa un istante solo insieme a un giorno (LocalDate::at()).
 */
final readonly class SlotTime implements Stringable
{
    /** "HH:MM" a 24 ore, con lo zero davanti: "07:00", mai "7:00" né "18:30:00". */
    public const string PATTERN = '/\A(?:[01]\d|2[0-3]):[0-5]\d\z/';

    /**
     * @param int $minutes minuti dalla mezzanotte, da 0 a 1439
     */
    private function __construct(
        public string $value,
        public int $minutes,
    ) {}

    /** L'orario scritto così com'è nella configurazione o nell'indirizzo, oppure null se non è valido. */
    public static function tryFrom(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }

        return new self($value, (int) substr($value, 0, 2) * 60 + (int) substr($value, 3, 2));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
