<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

/**
 * Com'è andato il salvataggio delle regole: salvate, oppure troppi tentativi (e quanto aspettare).
 */
final readonly class RulesOutcome
{
    /**
     * @param int $retryAfter secondi di attesa, se non sono state salvate
     */
    private function __construct(
        public bool $saved,
        public int $retryAfter = 0,
    ) {}

    public static function saved(): self
    {
        return new self(true);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(false, $retryAfter);
    }
}
