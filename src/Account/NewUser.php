<?php

declare(strict_types=1);

namespace FourStreet\Account;

use DateTimeImmutable;

/**
 * I dati di una registrazione, pronti per il database: la password è già un hash.
 */
final readonly class NewUser
{
    /**
     * @param DateTimeImmutable $at istante della registrazione, che è anche quello dell'accettazione dell'informativa
     * @param string $browserHash impronta del cookie del browser che ha inviato la registrazione
     */
    public function __construct(
        public FullName $fullName,
        public EmailAddress $email,
        public string $passwordHash,
        public DateTimeImmutable $at,
        public string $browserHash,
    ) {}
}
