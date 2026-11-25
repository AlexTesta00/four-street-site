<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Clock\Clock;

/**
 * Orologio fermo: i test non dipendono mai dall'ora reale né dal fuso della macchina.
 */
final readonly class FrozenClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    /**
     * @param string $time "2026-12-06 15:00:00" (anche con i microsecondi: "…:00.250000")
     */
    public static function at(string $time, string $timezone = 'Europe/Rome'): self
    {
        return new self(new DateTimeImmutable($time, new DateTimeZone($timezone)));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
