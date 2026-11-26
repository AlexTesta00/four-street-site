<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use InvalidArgumentException;

/**
 * Un indirizzo email con il nome da mostrare: "Mario Rossi <mario@example.com>".
 */
final readonly class Mailbox
{
    public function __construct(
        public string $email,
        public string $name = '',
    ) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('Indirizzo email non valido: "%s".', $email));
        }
        // Un a capo nel nome inietterebbe intestazioni nel messaggio.
        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new InvalidArgumentException('Il nome di un indirizzo email sta su una riga.');
        }
    }
}
