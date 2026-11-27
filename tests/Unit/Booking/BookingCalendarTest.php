<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\FirstFreeSeat;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Occupancy;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Booking\WeeklySchedule;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\SiteConfig;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Il calendario delle fasce, senza database: quali esistono (orario della settimana, chiusure,
 * inaugurazione, cambio dell'ora) e fino a quando si prenotano e si disdicono.
 * Le ore sono di San Marino; i confronti si fanno sugli istanti.
 */
#[CoversClass(BookingCalendar::class)]
#[CoversClass(ScheduledSlot::class)]
#[CoversClass(Occupancy::class)]
#[UsesClass(BookingRules::class)]
#[UsesClass(WeeklySchedule::class)]
#[UsesClass(ExampleSlotCatalog::class)]
#[UsesClass(LocalDate::class)]
#[UsesClass(SlotTime::class)]
#[UsesClass(Weekday::class)]
#[UsesClass(Slot::class)]
#[UsesClass(FirstFreeSeat::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(SiteConfig::class)]
final class BookingCalendarTest extends TestCase
{
    public function testTheBookableDaysStartTodayOrOnTheOpeningDay(): void
    {
        $calendar = self::calendar();

        // Il 25 novembre si vede dal giorno dell'inaugurazione (6 dicembre) a fra quattordici giorni (9 dicembre).
        self::assertSame(['2026-12-06', '2026-12-07', '2026-12-08', '2026-12-09'], self::values($calendar->days(self::rome('2026-11-25 08:00'))));
        // Dopo l'apertura, da oggi a fra quattordici giorni, compresi.
        $days = $calendar->days(self::rome('2026-12-10 23:59'));
        self::assertCount(15, $days);
        self::assertSame('2026-12-10', $days[0]->value);
        self::assertSame('2026-12-24', $days[14]->value);
        // Con days_ahead a 0, solo oggi.
        self::assertSame(['2026-12-10'], self::values(self::calendar(['days_ahead' => 0])->days(self::rome('2026-12-10 09:00'))));
        // L'inaugurazione ancora oltre: nessun giorno.
        self::assertSame([], self::calendar(['days_ahead' => 3])->days(self::rome('2026-11-25 08:00')));
    }

    public function testTodayIsTheLocalDay(): void
    {
        // Le 23:30 UTC del 9 dicembre sono già il 10 a San Marino.
        self::assertSame('2026-12-10', self::calendar()->today(new DateTimeImmutable('2026-12-09 23:30', new DateTimeZone('UTC')))->value);
    }

    public function testTheSlotsOfADayFollowTheWeeklySchedule(): void
    {
        $calendar = self::calendar();

        $monday = $calendar->slotsOn(self::day('2026-12-14'));
        self::assertCount(6, $monday);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], array_map(static fn(ScheduledSlot $slot): string => $slot->time->value, $monday));
        self::assertSame('2026-12-14 18:30', $monday[4]->key());
        self::assertSame('2026-12-14T18:30:00+01:00', $monday[4]->startsAt->format(DATE_ATOM));
        self::assertSame('2026-12-14T19:30:00+01:00', $monday[4]->endsAt->format(DATE_ATOM));
        self::assertTrue($monday[4]->is(self::day('2026-12-14'), self::time('18:30')));
        self::assertSame([], $calendar->slotsOn(self::day('2026-12-13')), 'La domenica è chiusa.');
        self::assertNull($calendar->slot(self::day('2026-12-14'), self::time('08:00')));
        self::assertSame('2026-12-14 07:00', $calendar->slot(self::day('2026-12-14'), self::time('07:00'))?->key());
    }

    public function testClosedDatesHaveNoSlots(): void
    {
        $calendar = self::calendar(['closed_dates' => ['2026-12-24', '2026-12-26']]);

        self::assertSame([], $calendar->slotsOn(self::day('2026-12-24')));
        self::assertCount(6, $calendar->slotsOn(self::day('2026-12-23')));
        self::assertNull($calendar->slot(self::day('2026-12-26'), self::time('18:30')));
    }

    public function testNothingBeforeTheOpeningAndOnTheOpeningDayOnlyFromItsHour(): void
    {
        // Un'apertura di sabato alle 15:00: quel giorno restano le fasce dalle 15:00 in poi.
        $calendar = self::calendar([], ['opening' => '2026-12-05 15:00']);

        self::assertSame([], $calendar->slotsOn(self::day('2026-12-04')));
        self::assertSame(['17:30', '18:30', '20:00'], array_map(static fn(ScheduledSlot $slot): string => $slot->time->value, $calendar->slotsOn(self::day('2026-12-05'))));

        // Una fascia che comincia proprio all'ora dell'apertura c'è.
        $atTheHour = self::calendar([], ['opening' => '2026-12-05 12:30']);
        self::assertSame(['12:30', '17:30', '18:30', '20:00'], array_map(static fn(ScheduledSlot $slot): string => $slot->time->value, $atTheHour->slotsOn(self::day('2026-12-05'))));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function bookingWindow(): iterable
    {
        // La fascia di lunedì 14 dicembre alle 18:30.
        yield 'quattordici giorni prima, a mezzanotte' => ['2026-11-30 00:00:00', true];
        yield 'quindici giorni prima: non ancora' => ['2026-11-29 23:59:59', false];
        yield 'la mattina stessa' => ['2026-12-14 08:00:00', true];
        yield 'un secondo prima dell\'inizio' => ['2026-12-14 18:29:59', true];
        yield 'all\'inizio' => ['2026-12-14 18:30:00', false];
        yield 'iniziata' => ['2026-12-14 18:45:00', false];
        yield 'il giorno dopo' => ['2026-12-15 08:00:00', false];
    }

    #[DataProvider('bookingWindow')]
    public function testASlotIsBookableFromDaysAheadUntilItStarts(string $now, bool $bookable): void
    {
        $calendar = self::calendar();
        $slot = $calendar->slot(self::day('2026-12-14'), self::time('18:30'));
        self::assertNotNull($slot);

        self::assertSame($bookable, $calendar->canBook($slot, self::rome($now)));
    }

    public function testBookingAndCancellingCanCloseEarlier(): void
    {
        $calendar = self::calendar(['booking_closes_minutes_before' => 30, 'cancel_closes_minutes_before' => 120]);
        $slot = $calendar->slot(self::day('2026-12-14'), self::time('18:30'));
        self::assertNotNull($slot);

        self::assertTrue($calendar->canBook($slot, self::rome('2026-12-14 17:59:59')));
        self::assertFalse($calendar->canBook($slot, self::rome('2026-12-14 18:00:00')));
        self::assertTrue($calendar->canCancel($slot->startsAt, self::rome('2026-12-14 16:29:59')));
        self::assertFalse($calendar->canCancel($slot->startsAt, self::rome('2026-12-14 16:30:00')));
    }

    public function testCancellingIsOpenUntilTheStartByDefault(): void
    {
        $calendar = self::calendar();
        $start = self::rome('2026-12-14 18:30');

        self::assertTrue($calendar->canCancel($start, self::rome('2026-12-14 18:29:59')));
        self::assertFalse($calendar->canCancel($start, self::rome('2026-12-14 18:30:00')));
        // Lo stesso istante scritto in UTC: conta l'istante, non il fuso.
        self::assertTrue($calendar->canCancel($start, new DateTimeImmutable('2026-12-14 17:29:59', new DateTimeZone('UTC'))));
    }

    public function testTheHourSkippedBySummerTimeHasNoSlot(): void
    {
        // Domenica 28 marzo 2027 si passa dalle 02:00 alle 03:00.
        $calendar = self::calendar(['schedule' => [...SiteFixture::week(), 'domenica' => ['01:30', '02:30', '03:30']]]);

        $slots = $calendar->slotsOn(self::day('2027-03-28'));

        self::assertCount(2, $slots);
        self::assertSame(['01:30', '03:30'], array_map(static fn(ScheduledSlot $slot): string => $slot->time->value, $slots));
        // La fascia delle 01:30 dura un'ora vera: finisce alle 03:30 dell'ora legale.
        self::assertSame('2027-03-28T03:30:00+02:00', $slots[0]->endsAt->format(DATE_ATOM));
        self::assertNull($calendar->slot(self::day('2027-03-28'), self::time('02:30')));
        // La domenica dopo la stessa ora esiste.
        self::assertNotNull($calendar->slot(self::day('2027-04-04'), self::time('02:30')));
    }

    public function testTheHourRepeatedByWinterTimeHasOneSlotOfRealDuration(): void
    {
        // Domenica 25 ottobre 2026 dalle 03:00 si torna alle 02:00: le 02:30 capitano due volte.
        $calendar = self::calendar(['schedule' => [...SiteFixture::week(), 'domenica' => ['01:30', '02:30']]], ['opening' => '2026-10-01 07:00']);

        $slots = $calendar->slotsOn(self::day('2026-10-25'));

        self::assertCount(2, $slots);
        // Un'ora vera dopo le 01:30 (ora legale) sono le 02:30 della prima volta.
        self::assertSame('2026-10-25T02:30:00+02:00', $slots[0]->endsAt->format(DATE_ATOM));
        self::assertSame(3600, $slots[1]->endsAt->getTimestamp() - $slots[1]->startsAt->getTimestamp());
    }

    public function testTheDaysAheadCountCalendarDaysAcrossTheChangeOfTime(): void
    {
        $calendar = self::calendar([], ['opening' => '2026-10-01 07:00']);

        // Da venerdì 23 ottobre, quattordici giorni dopo è il 6 novembre, anche se in mezzo c'è un giorno di 25 ore.
        $days = $calendar->days(self::rome('2026-10-23 23:30'));
        self::assertCount(15, $days);
        self::assertSame('2026-11-06', $days[14]->value);
        $slot = $calendar->slot(self::day('2026-11-06'), self::time('20:00'));
        self::assertNotNull($slot);
        self::assertTrue($calendar->canBook($slot, self::rome('2026-10-23 23:30')));
    }

    public function testTheSeatingOfADay(): void
    {
        $calendar = self::calendar();
        $occupancy = new Occupancy(['2026-12-14 18:30' => [1, 3], '2026-12-14 20:00' => [1, 2, 3, 4, 5]]);

        $slots = $calendar->seating(self::day('2026-12-14'), $occupancy, self::rome('2026-12-14 10:00'));

        self::assertCount(6, $slots);
        self::assertTrue($slots[0]->closed, 'Le 07:00 sono passate.');
        self::assertTrue($slots[1]->closed, 'Le 09:30 sono passate.');
        self::assertSame([1, 3], $slots[4]->takenSeats);
        self::assertSame(2, $slots[4]->nextSeat());
        self::assertTrue($slots[5]->isFull());
        self::assertSame([], $occupancy->seatsOf(self::day('2026-12-15'), self::time('18:30')));
    }

    /**
     * @param array<string, mixed> $booking
     * @param array<string, mixed> $site
     */
    private static function calendar(array $booking = [], array $site = []): BookingCalendar
    {
        return BookingCalendar::forSite(SiteFixture::config(['booking' => SiteFixture::booking($booking), ...$site]));
    }

    private static function rome(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('Europe/Rome'));
    }

    private static function day(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail(sprintf('Giorno non valido: %s.', $value));
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? self::fail(sprintf('Orario non valido: %s.', $value));
    }

    /**
     * @param list<LocalDate> $days
     *
     * @return list<string>
     */
    private static function values(array $days): array
    {
        return array_map(static fn(LocalDate $day): string => $day->value, $days);
    }
}
