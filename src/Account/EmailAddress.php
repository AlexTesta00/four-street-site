<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Mail\Mailbox;
use Stringable;

/**
 * L'email di un account, normalizzata: senza spazi ai lati e tutta in minuscolo, così "Mario@Example.com"
 * e "mario@example.com" sono lo stesso account. È la chiave unica degli utenti.
 */
final readonly class EmailAddress implements Stringable
{
    /** Lunghezza massima di un indirizzo (RFC 5321). */
    public const int MAX_LENGTH = 254;

    private function __construct(
        public string $value,
    ) {}

    /** L'indirizzo normalizzato, oppure null se non è un indirizzo valido. */
    public static function tryFrom(string $input): ?self
    {
        $email = strtolower(trim($input));
        if ($email === '' || strlen($email) > self::MAX_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return new self($email);
    }

    public function mailbox(string $name = ''): Mailbox
    {
        return new Mailbox($this->value, $name);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
