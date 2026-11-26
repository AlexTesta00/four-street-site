<?php

declare(strict_types=1);

namespace FourStreet\Account\Token;

use DateTimeImmutable;

/**
 * Il link monouso di un'email, come sta nel database: solo l'impronta del token, mai il token.
 * Un link usato resta nel database fino alla scadenza: così si distingue "già usato" da "inesistente".
 */
final readonly class AccountToken
{
    /**
     * @param string|null $returnPath la pagina a cui tornare dopo (registrazione → conferma → prenotazione)
     */
    public function __construct(
        public string $tokenHash,
        public int $userId,
        public TokenPurpose $purpose,
        public ?string $returnPath,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt = null,
    ) {}

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }
}
