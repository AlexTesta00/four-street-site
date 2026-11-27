<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Schedule\ChangeKind;
use FourStreet\Booking\Schedule\ChangePlan;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Schedule\DayPlan;
use FourStreet\Booking\Schedule\DayTimes;
use FourStreet\Booking\Schedule\DayWrite;
use FourStreet\Booking\Schedule\FileSchedule;
use FourStreet\Booking\Schedule\RulesChange;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Booking\Schedule\WeekdayWrite;
use FourStreet\Booking\SlotCounts;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\DayTime;
use FourStreet\Booking\Staff\Month;
use FourStreet\Booking\Staff\MonthDay;
use FourStreet\Booking\Staff\MonthSheet;
use FourStreet\Booking\Staff\WeekSheetDay;
use FourStreet\Booking\Weekday;
use FourStreet\Tests\Support\SiteFixture;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * I valori dell'orario che il titolare cambia, senza database: gli orari di un giorno, le modifiche chieste, le regole
 * del pannello, l'orario del file, i mesi e le settimane del pannello.
 */
#[CoversClass(DayTimes::class)]
#[CoversClass(ScheduleChange::class)]
#[CoversClass(RulesChange::class)]
#[CoversClass(BookingRules::class)]
#[CoversClass(FileSchedule::class)]
#[CoversClass(DayPlan::class)]
#[CoversClass(DayWrite::class)]
#[CoversClass(WeekdayWrite::class)]
#[CoversClass(ChangePlan::class)]
#[CoversClass(SlotCounts::class)]
#[CoversClass(Month::class)]
#[CoversClass(MonthSheet::class)]
#[CoversClass(MonthDay::class)]
#[CoversClass(WeekSheetDay::class)]
#[CoversClass(DayTime::class)]
final class ScheduleValuesTest extends TestCase
{
    public function testTheTimesOfADayAreSortedWithoutRepeats(): void
    {
        self::assertSame(['07:00', '18:30', '20:00'], self::values(DayTimes::sorted(self::times('20:00', '07:00', '18:30', '07:00'))));
        self::assertTrue(DayTimes::same(self::times('18:30', '07:00'), self::times('07:00', '18:30', '07:00')));
        self::assertFalse(DayTimes::same(self::times('07:00'), self::times('07:00', '18:30')));
        self::assertSame(['07:00', '20:00'], self::values(DayTimes::without(self::times('07:00', '18:30', '20:00'), self::times('18:30', '21:00'))));
    }

    /**
     * @return iterable<string, array{list<string>, int, string|null}>
     */
    public static function dayTimes(): iterable
    {
        yield 'vanno bene' => [['07:00', '08:00', '23:00'], 60, null];
        yield 'si sovrappongono' => [['18:00', '18:30'], 60, 'Le fasce delle 18:00 e delle 18:30 si sovrappongono: ogni fascia dura 60 minuti.'];
        yield 'oltre la mezzanotte' => [['23:30'], 60, 'La fascia delle 23:30 finirebbe dopo la mezzanotte: ogni fascia dura 60 minuti.'];
        yield 'fino alla mezzanotte esatta' => [['23:00'], 60, null];
        yield 'fasce corte' => [['18:00', '18:30'], 30, null];
        yield 'nessuna' => [[], 60, null];
    }

    /**
     * @param list<string> $times
     * @param positive-int $minutes
     */
    #[DataProvider('dayTimes')]
    public function testTheTimesOfADayFollowTheRulesOfTheFile(array $times, int $minutes, ?string $problem): void
    {
        self::assertSame($problem, DayTimes::problem(DayTimes::sorted(self::times(...$times)), $minutes));
    }

    public function testADayHasAtMostTwentyFourSlots(): void
    {
        $times = [];
        for ($hour = 0; $hour < 24; ++$hour) {
            $times[] = sprintf('%02d:00', $hour);
        }
        self::assertNull(DayTimes::problem(self::times(...$times), 59));
        self::assertSame('Al massimo 24 fasce al giorno.', DayTimes::problem([...self::times(...$times), self::time('23:30')], 1));
    }

    public function testAChangeCarriesOnlyWhatItNeeds(): void
    {
        $add = ScheduleChange::addWeekdayTime(Weekday::Monday, self::time('21:00'));
        self::assertSame(ChangeKind::AddWeekdayTime, $add->kind);
        self::assertTrue($add->isWeekly());
        self::assertSame(Weekday::Monday, $add->weekday());
        self::assertSame('21:00', $add->time()->value);

        $close = ScheduleChange::close(self::day('2026-12-24'), self::day('2026-12-26'), 'ferie');
        self::assertFalse($close->isWeekly());
        self::assertSame(['2026-12-24', '2026-12-25', '2026-12-26'], array_map(static fn(LocalDate $day): string => $day->value, $close->days()));
        self::assertSame('ferie', $close->note);

        $copy = ScheduleChange::copyWeek(self::day('2026-12-07'));
        self::assertSame('2026-12-13', $copy->to()->value);
        self::assertCount(7, $copy->days());

        $this->expectException(LogicException::class);
        $close->time();
    }

    /**
     * @return iterable<string, array{callable(): ScheduleChange}>
     */
    public static function wrongChanges(): iterable
    {
        yield 'la fine prima dell\'inizio' => [static fn(): ScheduleChange => ScheduleChange::close(self::day('2026-12-26'), self::day('2026-12-24'), null)];
        yield 'più di due mesi' => [static fn(): ScheduleChange => ScheduleChange::reopen(self::day('2026-12-01'), self::day('2027-02-01'))];
        yield 'una settimana che non comincia di lunedì' => [static fn(): ScheduleChange => ScheduleChange::copyWeek(self::day('2026-12-08'))];
        yield 'una nota da pulire' => [static fn(): ScheduleChange => ScheduleChange::close(self::day('2026-12-24'), self::day('2026-12-24'), '  ferie  ')];
    }

    /**
     * @param callable(): ScheduleChange $change
     */
    #[DataProvider('wrongChanges')]
    public function testAWrongChangeIsAProgrammingError(callable $change): void
    {
        $this->expectException(InvalidArgumentException::class);

        $change();
    }

    public function testSixtyTwoDaysAreTheLongestRange(): void
    {
        self::assertCount(62, ScheduleChange::close(self::day('2026-12-01'), self::day('2027-01-31'), null)->days());
    }

    public function testTheNoteIsCleanedOrRefused(): void
    {
        self::assertSame('ferie di Natale', ScheduleChange::normalizeNote("  ferie \t di   Natale "));
        self::assertNull(ScheduleChange::normalizeNote('   '));
        self::assertNull(ScheduleChange::normalizeNote(str_repeat('a', 101)));
        self::assertSame(str_repeat('à', 100), ScheduleChange::normalizeNote(str_repeat('à', 100)));
        self::assertNull(ScheduleChange::normalizeNote("ferie\x07"));
    }

    public function testThePanelRulesHaveTheLimitsOfTheFile(): void
    {
        $rules = new RulesChange(false, 30, 60, 120, 3);
        self::assertSame(['online' => '0', 'days_ahead' => '30', 'booking_closes_minutes_before' => '60', 'cancel_closes_minutes_before' => '120', 'max_upcoming' => '3'], $rules->values());
        self::assertArrayNotHasKey('online', (new RulesChange(null, 14, 0, 0, 6))->values(), 'Senza scelta resta quella di prima.');

        $file = SiteFixture::config()->booking;
        $of = RulesChange::of($file);
        self::assertSame([true, 14, 0, 0, 6], [$of->online, $of->daysAhead, $of->bookingClosesMinutesBefore, $of->cancelClosesMinutesBefore, $of->maxUpcoming]);

        foreach ([
            'giorni oltre i due mesi' => static fn(): RulesChange => new RulesChange(true, 63, 0, 0, 1),
            'giorni negativi' => static fn(): RulesChange => new RulesChange(true, -1, 0, 0, 1),
            'prenotazione chiusa oltre una settimana prima' => static fn(): RulesChange => new RulesChange(true, 0, 10081, 0, 1),
            'disdetta con minuti negativi' => static fn(): RulesChange => new RulesChange(true, 0, 0, -5, 1),
            'troppe prenotazioni future' => static fn(): RulesChange => new RulesChange(true, 0, 0, 0, 63),
        ] as $case => $make) {
            try {
                $make();
                self::fail('Fuori dai limiti: ' . $case);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheFileSwitchesOffBookingsForGood(): void
    {
        $on = SiteFixture::config()->booking;
        $changed = $on->with(online: false, daysAhead: 30, bookingClosesMinutesBefore: 15, cancelClosesMinutesBefore: 60, maxUpcoming: 2);
        self::assertSame([false, 30, 15, 60, 2], [$changed->online, $changed->daysAhead, $changed->bookingClosesMinutesBefore, $changed->cancelClosesMinutesBefore, $changed->maxUpcoming]);
        self::assertSame([5, 60], [$changed->capacity, $changed->slotMinutes], 'Posti e durata restano quelli del file.');
        self::assertSame($on->schedule, $on->with()->schedule, 'L\'orario del file resta quello del file.');
        self::assertSame([true, 14, 6], [$on->with()->online, $on->with()->daysAhead, $on->with()->maxUpcoming], 'Senza valori resta tutto com\'è.');

        $off = SiteFixture::config(['booking' => SiteFixture::booking(['online' => false])])->booking;
        self::assertFalse($off->with(online: true)->online, 'Spente nel file, il pannello non le accende.');
        self::assertSame('fino a 2 ore prima dell\'inizio', BookingRules::deadline(120));
    }

    public function testTheFileScheduleIsTheWeekAndTheClosedDates(): void
    {
        $schedule = new FileSchedule(SiteFixture::config(['booking' => SiteFixture::booking(['closed_dates' => ['2026-12-08']])])->booking);

        $monday = $schedule->day(self::day('2026-12-07'));
        self::assertSame(DayKind::Week, $monday->kind);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], self::values($monday->times));
        self::assertFalse($monday->isException());
        self::assertTrue($monday->has(self::time('18:30')));
        self::assertFalse($monday->has(self::time('08:00')));

        $closed = $schedule->day(self::day('2026-12-08'));
        self::assertTrue($closed->isClosed());
        self::assertTrue($closed->isException());
        self::assertSame([], $closed->times);

        $sunday = $schedule->day(self::day('2026-12-13'));
        self::assertSame(DayKind::Week, $sunday->kind, 'La domenica senza fasce segue la settimana tipo: non è una chiusura.');
        self::assertFalse($sunday->isClosed());
        self::assertSame([], $schedule->week()->on(Weekday::Sunday));
        self::assertSame(14, $schedule->rules()->daysAhead);
    }

    public function testAChangeRemovesOnlyTheTimesThatDisappear(): void
    {
        $day = self::day('2026-12-07');
        $removed = new DayWrite($day, DayKind::Changed, self::times('07:00', '18:30'), self::times('07:00'));
        self::assertTrue($removed->removes(self::time('18:30')));
        self::assertFalse($removed->removes(self::time('07:00')));
        self::assertFalse($removed->removes(self::time('12:30')), 'Una prenotazione già fuori dall\'orario resta com\'è.');

        $closed = new DayWrite($day, DayKind::Closed, self::times('07:00'), [], 'ferie');
        self::assertTrue($closed->removes(self::time('12:30')), 'Un giorno chiuso non tiene nessuna prenotazione.');

        $week = new WeekdayWrite(Weekday::Monday, self::times('07:00', '18:30'), self::times('18:30'), self::day('2026-11-26'));
        self::assertTrue($week->removes(self::time('07:00')));
        self::assertFalse($week->removes(self::time('18:30')));
    }

    public function testThePlanFingerprintIsTheSortedBookingNumbers(): void
    {
        $change = ScheduleChange::close(self::day('2026-12-07'), self::day('2026-12-07'), null);
        $plan = new ChangePlan($change, days: [new DayWrite(self::day('2026-12-07'), DayKind::Closed, [], [])], affected: [self::entry(15), self::entry(3), self::entry(40)]);

        self::assertSame('3,15,40', $plan->fingerprint());
        self::assertSame('', ChangePlan::fingerprintOf([]));
        self::assertTrue($plan->closes(self::entry(3)));
        self::assertFalse($plan->isRefused());
        self::assertFalse($plan->changesNothing());
        self::assertTrue((new ChangePlan($change))->changesNothing());
    }

    public function testSlotCountsAreReadByDayAndTime(): void
    {
        $counts = new SlotCounts(['2026-12-07' => ['07:00' => 2, '18:30' => 5]]);

        self::assertSame(5, $counts->of(self::day('2026-12-07'), self::time('18:30')));
        self::assertSame(0, $counts->of(self::day('2026-12-07'), self::time('09:30')));
        self::assertSame(['07:00' => 2, '18:30' => 5], $counts->on(self::day('2026-12-07')));
        self::assertSame([], $counts->on(self::day('2026-12-08')));
    }

    public function testAMonthKnowsItsDays(): void
    {
        $february = Month::tryFrom('2028-02') ?? self::fail();
        self::assertSame(['2028-02-01', '2028-02-29'], [$february->first()->value, $february->last()->value]);
        self::assertSame(['2028-01', '2028-03'], [$february->previous()->value, $february->next()->value]);
        self::assertSame('2026-12', (string) Month::of(self::day('2026-12-24')));
        self::assertTrue(Month::of(self::day('2026-12-01'))->equals(Month::of(self::day('2026-12-31'))));
        foreach (['2026-13', '2026-00', '26-12', '2026-1', 'dicembre', '2026-12-01'] as $wrong) {
            self::assertNull(Month::tryFrom($wrong), $wrong);
        }
    }

    public function testTheMonthIsSplitIntoWeeksFromMonday(): void
    {
        $month = Month::tryFrom('2026-12') ?? self::fail();
        $days = [];
        for ($date = $month->first(); !$date->isAfter($month->last()); $date = $date->addDays(1)) {
            $days[] = new MonthDay($date, new DayPlan($date, DayKind::Week, []), 0, $date->value === '2026-12-07' ? 3 : 0, 0, $date->value === '2026-12-07' ? 1 : 0, false, false, false);
        }
        $sheet = new MonthSheet($month, $month, self::day('2026-12-01'), $days, null, null, 5);

        // Ogni settimana dal suo primo all'ultimo giorno.
        $bounds = array_map(
            /** @param non-empty-list<MonthDay> $week */
            static fn(array $week): array => [$week[0]->date->value, array_reverse($week)[0]->date->value],
            $sheet->weeks(),
        );
        self::assertSame([
            ['2026-12-01', '2026-12-06'],
            ['2026-12-07', '2026-12-13'],
            ['2026-12-14', '2026-12-20'],
            ['2026-12-21', '2026-12-27'],
            ['2026-12-28', '2026-12-31'],
        ], $bounds, 'Da martedì 1 a giovedì 31 dicembre 2026, da lunedì a domenica.');
        self::assertSame(3, $sheet->booked());
        self::assertSame(1, $sheet->outOfSchedule());
        self::assertTrue($sheet->isCurrent());
        self::assertTrue(($days[0] ?? self::fail())->isClosed());
    }

    public function testWhatTheOwnerCanDoWithADayOfTheWeek(): void
    {
        $date = self::day('2026-12-07');
        $open = new DayPlan($date, DayKind::Week, self::times('07:00', '18:30'));
        $times = [new DayTime(self::time('07:00'), 2, true, true), new DayTime(self::time('18:30'), 1, false, true)];

        $today = new WeekSheetDay($date, $open, $open->times, $times, 1, true, false, false);
        self::assertTrue($today->isEditable());
        self::assertTrue($today->canAdd());
        self::assertFalse($today->canRemove($times[0]), 'Già cominciata.');
        self::assertTrue($today->canRemove($times[1]));
        self::assertFalse($today->canClose(), 'Oggi una fascia è già cominciata.');
        self::assertFalse($today->canReopen());
        self::assertFalse($today->canGoBackToWeek());
        self::assertSame(4, $today->booked());

        $closed = new WeekSheetDay($date, new DayPlan($date, DayKind::Closed, [], 'ferie'), $open->times, [], 0, false, false, false);
        self::assertTrue($closed->canReopen());
        self::assertFalse($closed->canAdd());
        self::assertFalse($closed->canClose());

        $changed = new WeekSheetDay($date, new DayPlan($date, DayKind::Changed, self::times('07:00')), $open->times, [new DayTime(self::time('07:00'), 0, false, true)], 0, false, false, false);
        self::assertTrue($changed->canGoBackToWeek());
        self::assertTrue($changed->canClose());

        $past = new WeekSheetDay($date, $open, $open->times, $times, 0, false, true, false);
        self::assertFalse($past->isEditable());
        self::assertFalse($past->canAdd());
        self::assertFalse((new WeekSheetDay($date, $open, $open->times, $times, 0, false, false, true))->isEditable(), 'Prima dell\'inaugurazione.');
    }

    private static function entry(int $id): RosterEntry
    {
        $start = new DateTimeImmutable('2026-12-07 17:30:00', new DateTimeZone('UTC'));

        return new RosterEntry(new Booking($id, null, 'Ospite Prova', self::day('2026-12-07'), self::time('18:30'), $start, 1, $start, CreatedBy::Admin), 'Ospite Prova', null);
    }

    /**
     * @return list<SlotTime>
     */
    private static function times(string ...$values): array
    {
        return array_values(array_map(self::time(...), $values));
    }

    /**
     * @param list<SlotTime> $times
     *
     * @return list<string>
     */
    private static function values(array $times): array
    {
        return array_map(static fn(SlotTime $time): string => $time->value, $times);
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? self::fail('Orario non valido: ' . $value);
    }

    private static function day(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail('Giorno non valido: ' . $value);
    }
}
