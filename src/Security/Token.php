<?php

declare(strict_types=1);

namespace FourStreet\Security;

use SensitiveParameter;

/**
 * Un token casuale da 256 bit, in base64url: per i cookie di sessione e CSRF e per i link delle email.
 * Il valore va solo al browser o nell'email; nel database finisce soltanto hash(), così chi leggesse
 * il database non potrebbe usare nessun token.
 */
final readonly class Token
{
    /** 32 byte in base64url senza "=" finali: 43 caratteri. */
    private const string PATTERN = '/\A[A-Za-z0-9_-]{43}\z/';

    private function __construct(
        #[SensitiveParameter]
        public string $value,
    ) {}

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    /**
     * Un token ricevuto (cookie, link, campo nascosto), oppure null se manca o non ha la forma giusta.
     */
    public static function fromString(#[SensitiveParameter] ?string $value): ?self
    {
        return $value !== null && preg_match(self::PATTERN, $value) === 1 ? new self($value) : null;
    }

    /** L'impronta da salvare e da cercare nel database (SHA-256, esadecimale). */
    public function hash(): string
    {
        return hash('sha256', $this->value);
    }
}
