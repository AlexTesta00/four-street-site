<?php

declare(strict_types=1);

namespace FourStreet\Security\RateLimit;

use DateTimeImmutable;

/**
 * Dove si contano i tentativi: un contatore per impronta e per finestra.
 */
interface RateLimitStore
{
    /**
     * Conta un tentativo nella finestra e restituisce il totale della finestra, questo compreso.
     * Con richieste insieme il totale letto può comprendere anche quelle degli altri, mai meno della propria:
     * il limite non si supera.
     *
     * @param string $bucket impronta del limite e del soggetto
     * @param DateTimeImmutable $expiresAt fine della finestra: da lì il contatore si può cancellare
     */
    public function increment(string $bucket, DateTimeImmutable $windowStart, DateTimeImmutable $expiresAt): int;

    /** Azzera il soggetto, in ogni finestra (per esempio dopo un accesso riuscito). */
    public function clear(string $bucket): void;

    /** Cancella i contatori delle finestre finite: restituisce quanti erano. */
    public function deleteExpired(DateTimeImmutable $now): int;
}
