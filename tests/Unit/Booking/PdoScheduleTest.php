<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Schedule\DayPlan;
use FourStreet\Booking\Schedule\FileSchedule;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\Schedule\RulesChange;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Booking\WeeklySchedule;
use FourStreet\Database\Database;
use FourStreet\Tests\Support\MovableClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * L'orario e le regole nel database, sul database dei test: SQLite in memoria, oppure MariaDB in CI (gruppo "database").
 * Finché il titolare non cambia nulla valgono quelli del file; la prima modifica copia nel database settimana tipo e
 * chiusure del file; la settimana tipo cambia da un giorno in poi e il passato resta com'era; ciò che si è letto resta
 * in memoria fino a forget().
 */
#[Group('database')]
#[CoversClass(PdoSchedule::class)]
#[CoversClass(WeeklySchedule::class)]
#[CoversClass(BookingCalendar::class)]
final class PdoScheduleTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private BookingRules $file;

    protected function setUp(): void
    {
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->file = SiteFixture::config(['booking' => SiteFixture::booking(['closed_dates' => ['2026-12-08', '2026-12-24']])])->booking;
    }

    public function testUntilTheOwnerChangesAnythingTheFileRules(): void
    {
        $schedule = $this->schedule();
        $file = new FileSchedule($this->file);

        self::assertFalse($schedule->isInDatabase());
        foreach (['2026-12-07', '2026-12-08', '2026-12-13'] as $date) {
            self::assertSame(self::plan($file->day(self::day($date))), self::plan($schedule->day(self::day($date))), $date);
        }
        self::assertSame($this->file->schedule, $schedule->week());
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], self::values($schedule->weekTimesOn(self::day('2026-12-08'))), 'La settimana tipo, senza la chiusura.');
        self::assertSame(['2026-12-08', '2026-12-24'], array_map(static fn(DayPlan $day): string => $day->date->value, $schedule->closuresFrom(self::day('2026-12-01'))));
        self::assertSame(['2026-12-24'], array_map(static fn(DayPlan $day): string => $day->date->value, $schedule->closuresFrom(self::day('2026-12-10'))));
        $rules = $schedule->rules();
        self::assertSame([true, 14, 0, 0, 6], [$rules->online, $rules->daysAhead, $rules->bookingClosesMinutesBefore, $rules->cancelClosesMinutesBefore, $rules->maxUpcoming]);
        self::assertSame(0, $this->rows('schedule_weekly_slots'), 'Leggere non copia nulla.');
    }

    public function testTheFirstChangeCopiesTheFileIntoTheDatabase(): void
    {
        $schedule = $this->schedule();
        $schedule->saveDay(self::day('2026-12-10'), true, [], 'corso di aggiornamento', null, $this->clock->now());

        self::assertTrue($schedule->isInDatabase());
        self::assertSame(36, $this->rows('schedule_weekly_slots'), 'Sei fasce per sei giorni.');
        self::assertSame(3, $this->rows('schedule_days'), 'Le due chiusure del file e quella nuova.');
        self::assertSame(DayKind::Closed, $schedule->day(self::day('2026-12-08'))->kind);
        self::assertNull($schedule->day(self::day('2026-12-08'))->note);
        self::assertSame('corso di aggiornamento', $schedule->day(self::day('2026-12-10'))->note);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], self::values($schedule->day(self::day('2026-12-07'))->times));
        self::assertSame([], $schedule->day(self::day('2026-12-13'))->times, 'La domenica resta senza fasce.');

        // Da qui il file non conta più: un file diverso non cambia l'orario.
        $changed = new PdoSchedule($this->database, SiteFixture::config(['booking' => SiteFixture::booking(['closed_dates' => []])])->booking);
        self::assertTrue($changed->day(self::day('2026-12-24'))->isClosed());

        // Una seconda modifica non copia di nuovo.
        $schedule->saveDay(self::day('2026-12-11'), true, [], null, null, $this->clock->now());
        self::assertSame(36, $this->rows('schedule_weekly_slots'));
    }

    public function testADayWithItsOwnTimesAndBackToTheWeek(): void
    {
        $schedule = $this->schedule();
        $schedule->saveDay(self::day('2026-12-09'), false, self::times('07:00', '21:00'), null, null, $this->clock->now());

        $day = $schedule->day(self::day('2026-12-09'));
        self::assertSame(DayKind::Changed, $day->kind);
        self::assertSame(['07:00', '21:00'], self::values($day->times));
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], self::values($schedule->weekTimesOn(self::day('2026-12-09'))));

        $schedule->resetDay(self::day('2026-12-09'), null, $this->clock->now());
        self::assertSame(DayKind::Week, $schedule->day(self::day('2026-12-09'))->kind);
        self::assertSame(0, $this->rows('schedule_day_slots', "slot_date = '2026-12-09'"));
    }

    public function testTheWeekChangesFromAGivenDayAndThePastStaysAsItWas(): void
    {
        $schedule = $this->schedule();
        $tomorrow = self::day('2026-11-26');
        $schedule->saveWeekday(Weekday::Monday, self::times('09:30', '18:30', '21:00'), $tomorrow, null, $this->clock->now());

        self::assertSame(['09:30', '18:30', '21:00'], self::values($schedule->week()->on(Weekday::Monday)));
        self::assertSame(['09:30', '18:30', '21:00'], self::values($schedule->day(self::day('2026-12-07'))->times));
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], self::values($schedule->day(self::day('2026-11-23'))->times), 'Un lunedì passato resta com\'era.');
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], self::values($schedule->day(self::day('2026-12-15'))->times), 'Il martedì non cambia.');

        // Cambiata di nuovo lo stesso giorno: le righe nate domani, che non hanno mai valso, spariscono.
        $schedule->saveWeekday(Weekday::Monday, self::times('09:30', '18:30'), $tomorrow, null, $this->clock->now());
        self::assertSame(0, $this->rows('schedule_weekly_slots', "slot_time = '21:00'"));
        self::assertSame(['09:30', '18:30'], self::values($schedule->day(self::day('2026-12-07'))->times));

        // Una settimana dopo, la stessa modifica vale da quel giorno: i lunedì di prima restano com'erano.
        $this->clock->moveTo('2026-12-09 10:00:00');
        $schedule->saveWeekday(Weekday::Monday, self::times('18:30'), self::day('2026-12-10'), null, $this->clock->now());
        self::assertSame(['09:30', '18:30'], self::values($schedule->day(self::day('2026-12-07'))->times));
        self::assertSame(['18:30'], self::values($schedule->day(self::day('2026-12-14'))->times));
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], self::values($schedule->day(self::day('2026-11-23'))->times));
    }

    public function testWhatHasBeenReadStaysUntilForgotten(): void
    {
        $schedule = $this->schedule();
        $schedule->saveDay(self::day('2026-12-09'), true, [], null, null, $this->clock->now());
        self::assertTrue($schedule->day(self::day('2026-12-09'))->isClosed());

        // Un'altra richiesta riapre il giorno: questa vede ancora l'orario che ha letto, finché non lo dimentica.
        $this->database->execute("DELETE FROM schedule_days WHERE slot_date = '2026-12-09'");
        self::assertTrue($schedule->day(self::day('2026-12-09'))->isClosed());
        self::assertTrue($schedule->day(self::day('2026-12-31'))->kind === DayKind::Week, 'Un altro giorno dello stesso mese: già letto con il mese.');
        $schedule->forget();
        self::assertFalse($schedule->day(self::day('2026-12-09'))->isClosed());

        // Il blocco dell'orario (la prima istruzione di una modifica) dimentica anche lui.
        $schedule->day(self::day('2026-12-09'));
        $this->database->execute("INSERT INTO schedule_days (slot_date, closed, note, updated_by, updated_at) VALUES ('2026-12-09', 1, NULL, NULL, '2026-11-25 07:00:00')");
        $schedule->transaction(static function () use ($schedule): void {
            $schedule->lock();
        });
        self::assertTrue($schedule->day(self::day('2026-12-09'))->isClosed());
    }

    public function testAnAbortedTransactionForgetsWhatItRead(): void
    {
        $schedule = $this->schedule();
        try {
            $schedule->transaction(function () use ($schedule): void {
                $schedule->saveDay(self::day('2026-12-09'), true, [], null, null, $this->clock->now());
                self::assertTrue($schedule->isInDatabase());

                throw new RuntimeException('Annullata.');
            });
        } catch (RuntimeException) {
            // Atteso.
        }

        self::assertFalse($schedule->isInDatabase(), 'Annullata: l\'orario non è nel database, e la memoria lo sa.');
        self::assertFalse($schedule->day(self::day('2026-12-09'))->isClosed());
        self::assertSame(0, $this->rows('booking_settings'));
    }

    public function testTheRulesSavedByTheOwnerReplaceThoseOfTheFile(): void
    {
        $schedule = $this->schedule();
        $schedule->saveRules(new RulesChange(false, 30, 60, 120, 2), null, $this->clock->now());

        $rules = $schedule->rules();
        self::assertSame([false, 30, 60, 120, 2], [$rules->online, $rules->daysAhead, $rules->bookingClosesMinutesBefore, $rules->cancelClosesMinutesBefore, $rules->maxUpcoming]);
        self::assertSame([5, 60], [$rules->capacity, $rules->slotMinutes]);
        self::assertFalse($schedule->isInDatabase(), 'Le regole non portano l\'orario nel database.');

        // Senza la scelta delle prenotazioni dal sito, resta quella di prima.
        $schedule->saveRules(new RulesChange(null, 20, 0, 0, 4), null, $this->clock->now());
        self::assertFalse($schedule->rules()->online);
        self::assertSame(20, $schedule->rules()->daysAhead);

        // Spente nel file, restano spente qualunque cosa abbia salvato il titolare.
        $schedule->saveRules(new RulesChange(true, 20, 0, 0, 4), null, $this->clock->now());
        $off = new PdoSchedule($this->database, SiteFixture::config(['booking' => SiteFixture::booking(['online' => false])])->booking);
        self::assertFalse($off->rules()->online);
        self::assertSame(20, $off->rules()->daysAhead);
    }

    public function testARuleThePanelNeverWritesIsAnError(): void
    {
        $this->database->execute("INSERT INTO booking_settings (name, value, updated_by, updated_at) VALUES ('days_ahead', '99', NULL, '2026-11-25 07:00:00')");

        $this->expectException(UnexpectedValueException::class);

        $this->schedule()->rules();
    }

    public function testTheCalendarReadsTheScheduleFromTheDatabase(): void
    {
        $schedule = $this->schedule();
        $schedule->saveDay(self::day('2026-12-09'), false, self::times('06:00', '21:00'), null, null, $this->clock->now());
        $calendar = BookingCalendar::from($schedule, SiteFixture::config());

        self::assertSame(['06:00', '21:00'], array_map(static fn(ScheduledSlot $slot): string => $slot->time->value, $calendar->slotsOn(self::day('2026-12-09'))));
        self::assertSame([], $calendar->slotsOn(self::day('2026-12-08')), 'Chiuso nel file, e copiato nel database.');
        self::assertSame(DayKind::Changed, $calendar->plan(self::day('2026-12-09'))->kind);
        self::assertNotNull($calendar->slot(self::day('2026-12-09'), self::time('21:00')));
        self::assertNull($calendar->slot(self::day('2026-12-09'), self::time('18:30')));
    }

    public function testRepeatedOpenRowsStillGiveOneTime(): void
    {
        $schedule = $this->schedule();
        $schedule->saveWeekday(Weekday::Monday, self::times('09:30'), self::day('2026-11-26'), null, $this->clock->now());
        $this->database->execute("INSERT INTO schedule_weekly_slots (weekday, slot_time, valid_from, valid_until) VALUES (1, '09:30', NULL, NULL)");
        $schedule->forget();

        self::assertSame(['09:30'], self::values($schedule->week()->on(Weekday::Monday)));
        self::assertSame(['09:30'], self::values($schedule->day(self::day('2026-12-07'))->times));
    }

    /**
     * L'orario di un giorno a parole: da dove viene, gli orari, la nota.
     *
     * @return array{string, list<string>, string|null}
     */
    private static function plan(DayPlan $plan): array
    {
        return [$plan->kind->name, self::values($plan->times), $plan->note];
    }

    private function schedule(): PdoSchedule
    {
        return new PdoSchedule($this->database, $this->file);
    }

    private function rows(string $table, string $where = '1 = 1'): int
    {
        return $this->database->fetchOne('SELECT COUNT(*) AS total FROM ' . $table . ' WHERE ' . $where)?->int('total') ?? 0;
    }

    /**
     * @return list<SlotTime>
     */
    private static function times(string ...$values): array
    {
        return array_values(array_map(static fn(string $value): SlotTime => SlotTime::tryFrom($value) ?? self::fail($value), $values));
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
        return SlotTime::tryFrom($value) ?? self::fail($value);
    }

    private static function day(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail($value);
    }
}
