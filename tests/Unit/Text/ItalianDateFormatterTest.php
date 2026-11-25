<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Text;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Text\ItalianDateFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItalianDateFormatter::class)]
final class ItalianDateFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function weekdays(): iterable
    {
        // Settimana dal 30 novembre al 6 dicembre 2026.
        yield 'lunedì' => ['2026-11-30', 'Lunedì 30 novembre 2026'];
        yield 'martedì' => ['2026-12-01', 'Martedì 1 dicembre 2026'];
        yield 'mercoledì' => ['2026-12-02', 'Mercoledì 2 dicembre 2026'];
        yield 'giovedì' => ['2026-12-03', 'Giovedì 3 dicembre 2026'];
        yield 'venerdì' => ['2026-12-04', 'Venerdì 4 dicembre 2026'];
        yield 'sabato' => ['2026-12-05', 'Sabato 5 dicembre 2026'];
        yield 'domenica' => ['2026-12-06', 'Domenica 6 dicembre 2026'];
    }

    #[DataProvider('weekdays')]
    public function testEveryWeekday(string $date, string $expected): void
    {
        self::assertSame($expected, (new ItalianDateFormatter())->format(self::rome($date)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function months(): iterable
    {
        yield 'gennaio' => ['2027-01-15', 'venerdì 15 gennaio'];
        yield 'febbraio' => ['2027-02-15', 'lunedì 15 febbraio'];
        yield 'marzo' => ['2027-03-15', 'lunedì 15 marzo'];
        yield 'aprile' => ['2027-04-15', 'giovedì 15 aprile'];
        yield 'maggio' => ['2027-05-15', 'sabato 15 maggio'];
        yield 'giugno' => ['2027-06-15', 'martedì 15 giugno'];
        yield 'luglio' => ['2027-07-15', 'giovedì 15 luglio'];
        yield 'agosto' => ['2027-08-15', 'domenica 15 agosto'];
        yield 'settembre' => ['2027-09-15', 'mercoledì 15 settembre'];
        yield 'ottobre' => ['2027-10-15', 'venerdì 15 ottobre'];
        yield 'novembre' => ['2027-11-15', 'lunedì 15 novembre'];
        yield 'dicembre' => ['2027-12-15', 'mercoledì 15 dicembre'];
    }

    #[DataProvider('months')]
    public function testEveryMonthInTheShortLowercaseForm(string $date, string $expected): void
    {
        self::assertSame($expected, (new ItalianDateFormatter())->format(self::rome($date), withYear: false, capitalize: false));
    }

    public function testDayOfMonthHasNoLeadingZero(): void
    {
        self::assertSame('Venerdì 1 gennaio 2027', (new ItalianDateFormatter())->format(self::rome('2027-01-01')));
    }

    public function testYearWithoutCapital(): void
    {
        self::assertSame('domenica 6 dicembre 2026', (new ItalianDateFormatter())->format(self::rome('2026-12-06'), capitalize: false));
        self::assertSame('Domenica 6 dicembre', (new ItalianDateFormatter())->format(self::rome('2026-12-06'), withYear: false));
    }

    public function testUsesTheTimezoneOfTheDate(): void
    {
        // Lo stesso istante: domenica sera a Roma, già lunedì a Tokyo.
        $rome = new DateTimeImmutable('2026-12-06 22:00', new DateTimeZone('Europe/Rome'));

        self::assertSame('Domenica 6 dicembre 2026', (new ItalianDateFormatter())->format($rome));
        self::assertSame('Lunedì 7 dicembre 2026', (new ItalianDateFormatter())->format($rome->setTimezone(new DateTimeZone('Asia/Tokyo'))));
    }

    private static function rome(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 12:00', new DateTimeZone('Europe/Rome'));
    }
}
