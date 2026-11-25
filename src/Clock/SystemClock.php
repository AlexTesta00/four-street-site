<?php

declare(strict_types=1);

namespace FourStreet\Clock;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Orologio di sistema, nel fuso indicato (mai quello predefinito della macchina).
 */
final readonly class SystemClock implements Clock
{
    public function __construct(
        private DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
