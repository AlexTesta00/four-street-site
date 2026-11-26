<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use DateTimeImmutable;

/**
 * Una sessione aperta, come sta nel database: l'impronta del token del cookie, mai il token.
 * Scade dopo un periodo senza visite; ogni visita la allunga.
 */
final readonly class Session
{
    public function __construct(
        public string $tokenHash,
        public int $userId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastSeenAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
