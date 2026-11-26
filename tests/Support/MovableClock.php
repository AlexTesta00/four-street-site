<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Clock\Clock;

/**
 * Orologio fermo che il test può spostare in avanti: per le scadenze (link di 48 ore, sessioni di 30 giorni).
 */
final class MovableClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    /**
     * @param string $time "2026-11-25 08:00:00", ora di San Marino
     */
    public static function at(string $time, string $timezone = 'Europe/Rome'): self
    {
        return new self(new DateTimeImmutable($time, new DateTimeZone($timezone)));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * @param string $modifier "+48 hours", "+30 days"…
     */
    public function travel(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
