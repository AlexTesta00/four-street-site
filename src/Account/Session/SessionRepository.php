<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use DateTimeImmutable;

/**
 * Le sessioni salvate, cercate per impronta del token.
 */
interface SessionRepository
{
    public function create(Session $session): void;

    public function find(string $tokenHash): ?Session;

    /** Registra una visita: la sessione dura di nuovo fino a $expiresAt. */
    public function touch(string $tokenHash, DateTimeImmutable $lastSeenAt, DateTimeImmutable $expiresAt): void;

    public function delete(string $tokenHash): void;

    /** Chiude tutte le sessioni dell'utente, su ogni dispositivo. */
    public function deleteForUser(int $userId): void;

    /** Cancella le sessioni scadute: restituisce quante erano. */
    public function deleteExpired(DateTimeImmutable $now): int;
}
