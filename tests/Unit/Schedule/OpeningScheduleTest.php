<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Schedule;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Schedule\Countdown;
use FourStreet\Schedule\OpeningSchedule;
use FourStreet\Schedule\Preview;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpeningSchedule::class)]
#[UsesClass(Countdown::class)]
final class OpeningScheduleTest extends TestCase
{
    private const string OPENING = '2026-12-06 15:00:00';

    /**
     * @return iterable<string, array{string, bool, bool, int}>
     */
    public static function instants(): iterable
    {
        // adesso (Europe/Rome) => in corso, finita, secondi che mancano
        yield 'undici giorni prima' => ['2026-11-25 08:00:00', false, false, 975_600];
        yield 'un secondo prima' => ['2026-12-06 14:59:59', false, false, 1];
        yield 'mezzo secondo prima' => ['2026-12-06 14:59:59.500000', false, false, 1];
        yield 'all\'istante esatto' => [self::OPENING, true, false, 0];
        yield 'un secondo dopo' => ['2026-12-06 15:00:01', true, false, 0];
        yield 'ultimo secondo del giorno' => ['2026-12-06 23:59:59', true, false, 0];
        yield 'mezzanotte del giorno dopo' => ['2026-12-07 00:00:00', true, true, 0];
        yield 'mesi dopo' => ['2027-03-01 10:00:00', true, true, 0];
    }

    #[DataProvider('instants')]
    public function testStateAroundTheOpening(string $now, bool $live, bool $over, int $secondsLeft): void
    {
        $schedule = new OpeningSchedule(self::rome(self::OPENING), self::rome($now));

        self::assertSame($live, $schedule->isLive());
        self::assertSame($over, $schedule->isEventOver());
        self::assertSame($secondsLeft, $schedule->secondsLeft());
        self::assertSame($secondsLeft, $schedule->countdown()->totalSeconds);
        self::assertSame('2026-12-06 15:00:00.000000 +01:00', self::exact($schedule->countdownTarget));
    }

    public function testTheEventEndsAtLocalMidnightEvenWhenNowIsInAnotherTimezone(): void
    {
        $opening = self::rome(self::OPENING);

        // 22:59:59 UTC = 23:59:59 a Roma: ancora il giorno dell'inaugurazione.
        $lastSecond = new OpeningSchedule($opening, new DateTimeImmutable('2026-12-06 22:59:59', new DateTimeZone('UTC')));
        // 23:00 UTC = mezzanotte a Roma.
        $midnight = new OpeningSchedule($opening, new DateTimeImmutable('2026-12-06 23:00:00', new DateTimeZone('UTC')));

        self::assertFalse($lastSecond->isEventOver());
        self::assertTrue($midnight->isEventOver());
    }

    public function testMapPreviewShowsTheLiveStateBeforeTheOpening(): void
    {
        $schedule = new OpeningSchedule(self::rome(self::OPENING), self::rome('2026-11-25 08:00:00'), Preview::Map);

        self::assertTrue($schedule->isLive());
        self::assertFalse($schedule->isEventOver());
        // Il countdown resta quello vero.
        self::assertSame('2026-12-06 15:00:00.000000 +01:00', self::exact($schedule->countdownTarget));
        self::assertSame(975_600, $schedule->secondsLeft());
    }

    public function testZeroPreviewTargetsTwelveSecondsFromNow(): void
    {
        $now = self::rome('2026-11-25 08:00:00.250000');
        $schedule = new OpeningSchedule(self::rome(self::OPENING), $now, Preview::Zero);

        self::assertSame('2026-11-25 08:00:12.250000 +01:00', self::exact($schedule->countdownTarget));
        self::assertFalse($schedule->isLive());
        self::assertSame(12, $schedule->secondsLeft());
        self::assertSame((1_795_590_000 + 12) * 1000, $schedule->countdownTargetMilliseconds());
    }

    public function testZeroPreviewShowsTheCountdownEvenAfterTheEvent(): void
    {
        $schedule = new OpeningSchedule(self::rome(self::OPENING), self::rome('2026-12-10 18:00:00'), Preview::Zero);

        self::assertFalse($schedule->isLive());
        self::assertTrue($schedule->isEventOver());
        self::assertSame(12, $schedule->secondsLeft());
    }

    public function testMillisecondsForTheScript(): void
    {
        $schedule = new OpeningSchedule(self::rome(self::OPENING), self::rome('2026-11-25 08:00:00'));

        self::assertSame(1_796_565_600_000, $schedule->countdownTargetMilliseconds());
        self::assertSame(1_795_590_000_000, $schedule->nowMilliseconds());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function fractions(): iterable
    {
        yield 'per difetto' => ['2026-11-25 08:00:00.250400', 1_795_590_000_250];
        yield 'mezzo millisecondo per eccesso' => ['2026-11-25 08:00:00.250500', 1_795_590_000_251];
        yield 'fino al secondo dopo' => ['2026-11-25 08:00:00.999600', 1_795_590_001_000];
    }

    #[DataProvider('fractions')]
    public function testNowMillisecondsAreRounded(string $now, int $milliseconds): void
    {
        $schedule = new OpeningSchedule(self::rome(self::OPENING), self::rome($now));

        self::assertSame($milliseconds, $schedule->nowMilliseconds());
    }

    private static function exact(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.u P');
    }

    private static function rome(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('Europe/Rome'));
    }
}
