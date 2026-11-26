<?php

declare(strict_types=1);

namespace FourStreet\Account;

/**
 * Nome e cognome in un campo solo, come li scrive la persona: niente regole sulle lettere
 * (apostrofi, accenti, trattini, nomi composti vanno tutti bene), solo spazi in ordine e una lunghezza sensata.
 */
final readonly class FullName
{
    public const int MIN_LENGTH = 2;
    public const int MAX_LENGTH = 100;

    private function __construct(
        public string $value,
    ) {}

    /** Il nome con gli spazi in ordine, oppure null se è troppo corto, troppo lungo o ha caratteri di controllo. */
    public static function tryFrom(string $input): ?self
    {
        $name = self::normalize($input);
        $length = mb_strlen($name);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH || preg_match('/[\p{Cc}\p{Cf}]/u', $name) === 1) {
            return null;
        }

        return new self($name);
    }

    /** Spazi ai lati tolti e quelli ripetuti ridotti a uno. */
    public static function normalize(string $input): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $input));
    }
}
