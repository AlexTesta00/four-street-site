<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Schedule;

use FourStreet\Schedule\Countdown;
use FourStreet\Schedule\CountdownUnit;
use FourStreet\Schedule\TimeLeft;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Countdown::class)]
#[CoversClass(CountdownUnit::class)]
#[CoversClass(TimeLeft::class)]
final class CountdownTest extends TestCase
{
    /**
     * @return iterable<string, array{int, array{int, int, int, int}}>
     */
    public static function durations(): iterable
    {
        yield 'zero' => [0, [0, 0, 0, 0]];
        yield '59 secondi' => [59, [0, 0, 0, 59]];
        yield 'un minuto' => [60, [0, 0, 1, 0]];
        yield 'un\'ora meno un secondo' => [3_599, [0, 0, 59, 59]];
        yield 'un\'ora' => [3_600, [0, 1, 0, 0]];
        yield 'un giorno meno un secondo' => [86_399, [0, 23, 59, 59]];
        yield 'un giorno' => [86_400, [1, 0, 0, 0]];
        yield '11 giorni e 7 ore' => [975_600, [11, 7, 0, 0]];
        yield 'misto' => [2 * 86_400 + 3 * 3_600 + 4 * 60 + 5, [2, 3, 4, 5]];
    }

    /**
     * @param array{int, int, int, int} $parts
     */
    #[DataProvider('durations')]
    public function testSplitsSecondsIntoUnits(int $seconds, array $parts): void
    {
        $countdown = new Countdown($seconds);

        self::assertSame($parts, [$countdown->days(), $countdown->hours(), $countdown->minutes(), $countdown->seconds()]);
        self::assertSame($parts, array_map(static fn(CountdownUnit $unit): int => $unit->value, $countdown->units()));
    }

    public function testUnitsHaveTheKeysAndLabelsUsedByThePage(): void
    {
        $units = (new Countdown(0))->units();

        self::assertSame(['giorni', 'ore', 'minuti', 'secondi'], array_map(static fn(CountdownUnit $unit): string => $unit->key, $units));
        self::assertSame(['Giorni', 'Ore', 'Minuti', 'Secondi'], array_map(static fn(CountdownUnit $unit): string => $unit->label, $units));
    }

    public function testRejectsNegativeDurations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Countdown(-1);
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function roughDurations(): iterable
    {
        yield 'un secondo: un\'ora' => [1, 1, 'ora'];
        yield 'un\'ora esatta' => [3_600, 1, 'ora'];
        yield 'un\'ora e un secondo: due ore' => [3_601, 2, 'ore'];
        yield 'tre ore' => [3 * 3_600, 3, 'ore'];
        yield 'l\'ultimo secondo prima del giorno' => [86_399, 24, 'ore'];
        yield 'un giorno' => [86_400, 1, 'giorno'];
        yield 'quasi due giorni: un giorno' => [2 * 86_400 - 1, 1, 'giorno'];
        yield 'due giorni' => [2 * 86_400, 2, 'giorni'];
        yield 'undici giorni' => [975_600, 11, 'giorni'];
    }

    #[DataProvider('roughDurations')]
    public function testRoughlyIsDaysOrHoursRoundedUp(int $seconds, int $value, string $unit): void
    {
        $left = (new Countdown($seconds))->roughly();

        self::assertSame($value, $left->value);
        self::assertSame($unit, $left->unit);
    }

    /**
     * @return iterable<string, array{int, list<string>}>
     */
    public static function digits(): iterable
    {
        yield 'zero' => [0, ['0', '0']];
        yield 'una cifra' => [7, ['0', '7']];
        yield 'due cifre' => [42, ['4', '2']];
        yield 'il massimo' => [99, ['9', '9']];
        yield 'oltre il massimo' => [140, ['9', '9']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('digits')]
    public function testUnitDigitsArePaddedAndCappedAt99(int $value, array $expected): void
    {
        self::assertSame($expected, (new CountdownUnit('giorni', 'Giorni', $value))->digits());
    }
}
