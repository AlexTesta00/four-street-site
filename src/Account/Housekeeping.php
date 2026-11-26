<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Account\Session\SessionRepository;
use FourStreet\Account\Token\AccountTokenRepository;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimitStore;

/**
 * Pulizia di ciò che è scaduto: sessioni, link delle email, contatori dei tentativi.
 * Gira da sola a ogni accesso (pulizia opportunistica) e con bin/console prune.
 */
final readonly class Housekeeping
{
    public function __construct(
        private SessionRepository $sessions,
        private AccountTokenRepository $tokens,
        private RateLimitStore $rateLimits,
        private Clock $clock,
    ) {}

    /**
     * @return array{sessions: int, tokens: int, rate_limits: int} quante righe ha cancellato, per tipo
     */
    public function run(): array
    {
        $now = $this->clock->now();

        return [
            'sessions' => $this->sessions->deleteExpired($now),
            'tokens' => $this->tokens->deleteExpired($now),
            'rate_limits' => $this->rateLimits->deleteExpired($now),
        ];
    }
}
