<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * I giorni della settimana, numerati come ISO 8601 (lunedì 1, domenica 7), con i nomi italiani
 * che si scrivono nell'orario di config/site.php.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    /**
     * Il giorno dal suo nome in config/site.php: "lunedì", anche senza accento ("lunedi") o in maiuscolo.
     */
    public static function fromName(string $name): ?self
    {
        $name = mb_strtolower(trim($name));
        foreach (self::cases() as $day) {
            if ($name === $day->label() || $name === strtr($day->label(), ['ì' => 'i'])) {
                return $day;
            }
        }

        return null;
    }

    /** "lunedì". */
    public function label(): string
    {
        return match ($this) {
            self::Monday => 'lunedì',
            self::Tuesday => 'martedì',
            self::Wednesday => 'mercoledì',
            self::Thursday => 'giovedì',
            self::Friday => 'venerdì',
            self::Saturday => 'sabato',
            self::Sunday => 'domenica',
        };
    }
}
