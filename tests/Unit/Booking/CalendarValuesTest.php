<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Giorni, orari e giorni della settimana delle fasce: i valori che arrivano dalla configurazione
 * e dagli indirizzi, validati, e i conti sul calendario (anche attraverso il cambio dell'ora).
 */
#[CoversClass(LocalDate::class)]
#[CoversClass(SlotTime::class)]
#[CoversClass(Weekday::class)]
final class CalendarValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'vuoto' => [''];
        yield 'giorno inesistente' => ['2026-02-30'];
        yield '31 aprile' => ['2026-04-31'];
        yield 'mese tredici' => ['2026-13-01'];
        yield 'senza zeri' => ['2026-12-7'];
        yield 'all\'italiana' => ['07/12/2026'];
        yield 'con l\'ora' => ['2026-12-07 18:30'];
        yield 'spazi intorno' => [' 2026-12-07'];
        yield 'a capo in fondo' => ["2026-12-07\n"];
    }

    #[DataProvider('invalidDates')]
    public function testOnlyExistingDaysWrittenAsYearMonthDay(string $value): void
    {
        self::assertNull(LocalDate::tryFrom($value));
    }

    public function testCalendarArithmetic(): void
    {
        $day = LocalDate::tryFrom('2026-12-30');
        self::assertNotNull($day);

        self::assertSame('2027-01-13', $day->addDays(14)->value);
        self::assertSame('2026-12-29', $day->addDays(-1)->value);
        self::assertSame('2026-12-30', (string) $day->addDays(0));
        self::assertSame('2028-02-29', LocalDate::tryFrom('2028-02-28')?->addDays(1)->value, 'Anno bisestile.');
        self::assertSame(Weekday::Wednesday, $day->weekday());
        self::assertSame(Weekday::Sunday, LocalDate::tryFrom('2026-12-06')?->weekday());
        self::assertSame(Weekday::Monday, LocalDate::tryFrom('2026-12-07')?->weekday());
    }

    public function testDaysAcrossTheChangeOfTimeAreStillWholeDays(): void
    {
        // Il 25 ottobre 2026 dura 25 ore e il 28 marzo 2027 23: i giorni restano giorni.
        self::assertSame('2026-10-26', LocalDate::tryFrom('2026-10-24')?->addDays(2)->value);
        self::assertSame('2027-03-29', LocalDate::tryFrom('2027-03-27')?->addDays(2)->value);
    }

    public function testComparisons(): void
    {
        $monday = LocalDate::tryFrom('2026-12-07');
        $tuesday = LocalDate::tryFrom('2026-12-08');
        self::assertNotNull($monday);
        self::assertNotNull($tuesday);

        self::assertTrue($monday->isBefore($tuesday));
        self::assertFalse($tuesday->isBefore($monday));
        self::assertTrue($tuesday->isAfter($monday));
        self::assertFalse($monday->isAfter($monday));
        self::assertTrue($monday->equals(LocalDate::tryFrom('2026-12-07') ?? $tuesday));
        self::assertSame('2026-12-07', $monday->calendarDay()->format('Y-m-d'));
    }

    public function testTheDayOfAnInstantIsTheLocalOne(): void
    {
        $rome = new DateTimeZone('Europe/Rome');

        // Le 00:30 di Roma sono ancora il giorno prima in UTC; le 23:30 UTC sono già il giorno dopo a Roma.
        self::assertSame('2026-12-07', LocalDate::of(new DateTimeImmutable('2026-12-07 00:30', $rome), $rome)->value);
        self::assertSame('2026-12-08', LocalDate::of(new DateTimeImmutable('2026-12-07 23:30', new DateTimeZone('UTC')), $rome)->value);
    }

    public function testTheInstantOfAnHourOnADay(): void
    {
        $rome = new DateTimeZone('Europe/Rome');
        $time = SlotTime::tryFrom('18:30');
        self::assertNotNull($time);

        $winter = LocalDate::tryFrom('2026-12-07')?->at($time, $rome);
        $summer = LocalDate::tryFrom('2027-07-05')?->at($time, $rome);

        self::assertSame('2026-12-07T17:30:00+00:00', $winter?->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM));
        self::assertSame('2027-07-05T16:30:00+00:00', $summer?->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM));
    }

    public function testTheHourSkippedBySummerTimeDoesNotExist(): void
    {
        $rome = new DateTimeZone('Europe/Rome');
        $day = LocalDate::tryFrom('2027-03-28');
        self::assertNotNull($day);

        self::assertNull($day->at(SlotTime::tryFrom('02:30') ?? self::fail(), $rome));
        self::assertSame('2027-03-28T03:00:00+02:00', $day->at(SlotTime::tryFrom('03:00') ?? self::fail(), $rome)?->format(DATE_ATOM));
    }

    public function testTheHourRepeatedByWinterTimeExistsOnce(): void
    {
        $rome = new DateTimeZone('Europe/Rome');
        $moment = LocalDate::tryFrom('2026-10-25')?->at(SlotTime::tryFrom('02:30') ?? self::fail(), $rome);

        self::assertNotNull($moment);
        self::assertSame('2026-10-25 02:30', $moment->format('Y-m-d H:i'));
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function times(): iterable
    {
        yield 'mezzanotte' => ['00:00', 0];
        yield 'mattina' => ['07:00', 420];
        yield 'sera' => ['18:30', 1110];
        yield 'ultimo minuto' => ['23:59', 1439];
        yield 'senza zero' => ['7:00', null];
        yield 'ventiquattro' => ['24:00', null];
        yield 'minuti oltre' => ['18:60', null];
        yield 'con i secondi' => ['18:30:00', null];
        yield 'con il punto' => ['18.30', null];
        yield 'vuoto' => ['', null];
    }

    #[DataProvider('times')]
    public function testSlotTimesAreTwentyFourHourClockTimes(string $value, ?int $minutes): void
    {
        $time = SlotTime::tryFrom($value);

        self::assertSame($minutes, $time?->minutes);
        if ($time !== null) {
            self::assertSame($value, (string) $time);
            self::assertTrue($time->equals(SlotTime::tryFrom($value) ?? self::fail()));
        }
    }

    /**
     * @return iterable<string, array{string, Weekday|null}>
     */
    public static function weekdayNames(): iterable
    {
        yield 'con l\'accento' => ['lunedì', Weekday::Monday];
        yield 'senza accento' => ['venerdi', Weekday::Friday];
        yield 'in maiuscolo' => ['Mercoledì', Weekday::Wednesday];
        yield 'con gli spazi' => [' sabato ', Weekday::Saturday];
        yield 'domenica' => ['domenica', Weekday::Sunday];
        yield 'abbreviato' => ['lun', null];
        yield 'in inglese' => ['monday', null];
    }

    #[DataProvider('weekdayNames')]
    public function testWeekdaysByTheirItalianName(string $name, ?Weekday $day): void
    {
        self::assertSame($day, Weekday::fromName($name));
    }

    public function testWeekdayLabels(): void
    {
        self::assertSame(
            ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'],
            array_map(static fn(Weekday $day): string => $day->label(), Weekday::cases()),
        );
    }
}
