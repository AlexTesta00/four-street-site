<?php

declare(strict_types=1);

namespace FourStreet\Security\RateLimit;

use InvalidArgumentException;

/**
 * Un limite di tentativi a finestre fisse: al massimo $maxAttempts in ogni finestra di $windowSeconds
 * (per esempio 20 accessi sbagliati ogni 15 minuti dallo stesso indirizzo IP).
 */
final readonly class RateLimit
{
    /**
     * @param string $name nome del limite: separa i contatori ("accesso-ip", "accesso-account"…)
     */
    public function __construct(
        public string $name,
        public int $maxAttempts,
        public int $windowSeconds,
    ) {
        if (preg_match('/\A[a-z0-9-]+\z/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Nome di limite non valido: "%s".', $name));
        }
        if ($maxAttempts < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException(sprintf('Il limite "%s" vuole tentativi e durata maggiori di zero.', $name));
        }
    }
}
