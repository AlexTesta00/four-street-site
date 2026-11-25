<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Clock;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testReturnsTheCurrentInstantInTheGivenTimezone(): void
    {
        $before = new DateTimeImmutable();
        $now = (new SystemClock(new DateTimeZone('Europe/Rome')))->now();
        $after = new DateTimeImmutable();

        self::assertSame('Europe/Rome', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    public function testUsesUtcUnlessToldOtherwise(): void
    {
        self::assertSame('UTC', (new SystemClock())->now()->getTimezone()->getName());
    }
}
