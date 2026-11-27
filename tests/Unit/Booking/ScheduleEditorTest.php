<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use Closure;
use DateTimeImmutable;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\User;
use FourStreet\Asset\AssetUrls;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\Schedule\CancelledBooking;
use FourStreet\Booking\Schedule\ChangeOutcome;
use FourStreet\Booking\Schedule\ChangePlan;
use FourStreet\Booking\Schedule\ChangeStatus;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Schedule\DayPlan;
use FourStreet\Booking\Schedule\DayWrite;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\Schedule\RulesChange;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Booking\Schedule\ScheduleConflict;
use FourStreet\Booking\Schedule\ScheduleEditor;
use FourStreet\Booking\Schedule\ScheduleStore;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Booking\WeeklySchedule;
use FourStreet\Config\SiteConfig;
use FourStreet\Database\Database;
use FourStreet\Http\Origin;
use FourStreet\Mail\MailTemplates;
use FourStreet\Security\Fingerprinter;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Tests\Support\CollectingReporter;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\MovableClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestDatabase;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Icons;
use FourStreet\View\View;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Le modifiche dell'orario fatte dal titolare, senza HTTP, sul database dei test (SQLite in memoria, MariaDB in CI):
 * le regole (il passato non si tocca, l'inaugurazione, il cambio dell'ora, le sovrapposizioni), la settimana tipo che
 * vale da domani, la conferma con l'impronta delle prenotazioni, le disdette con le email e le gare.
 * L'orologio parte da mercoledì 25 novembre 2026, 08:00; l'inaugurazione è domenica 6 dicembre alle 15:00.
 */
#[Group('database')]
#[CoversClass(ScheduleEditor::class)]
#[CoversClass(ChangePlan::class)]
#[CoversClass(ChangeOutcome::class)]
#[CoversClass(PdoSchedule::class)]
final class ScheduleEditorTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private InMemoryMailer $mailer;

    private CollectingReporter $reporter;

    private SiteConfig $site;

    private PdoSchedule $schedule;

    private User $staff;

    protected function setUp(): void
    {
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->mailer = new InMemoryMailer();
        $this->reporter = new CollectingReporter();
        $this->site = SiteFixture::config();
        $this->schedule = new PdoSchedule($this->database, $this->site->booking);
        $this->staff = $this->user('titolare@example.test', 'Titolare Prova');
    }

    public function testTheWeekChangesFromTomorrowAndLeavesTodayAndOwnTimesAlone(): void
    {
        // Lunedì 7 dicembre alle 08:00: oggi è lunedì.
        $this->clock->moveTo('2026-12-07 08:00:00');
        $editor = $this->editor();
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-14'), self::time('21:00')))->status);

        $outcome = $editor->apply($this->staff, ScheduleChange::addWeekdayTime(Weekday::Monday, self::time('06:00')));

        self::assertSame(ChangeStatus::Applied, $outcome->status);
        self::assertSame('2026-12-08', $outcome->plan()->weekday?->from->value);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], $this->times('2026-12-07'), 'Oggi resta com\'è.');
        self::assertSame(['06:00', '07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], $this->times('2026-12-21'));
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00', '21:00'], $this->times('2026-12-14'), 'Un giorno con un orario suo non segue la settimana tipo.');
    }

    public function testRemovingATimeFromTheWeekCancelsOnlyFutureBookingsOnDaysThatFollowIt(): void
    {
        $anna = $this->user('anna@example.test', 'Anna Bianchi');
        $luca = $this->user('luca@example.test', 'Luca Verdi');
        $monday = $this->book($anna, '2026-12-07', '18:30');
        $tuesday = $this->book($luca, '2026-12-08', '18:30');
        $editor = $this->editor();
        // Il 14 ha un orario suo, con le 18:30: la settimana tipo non lo tocca.
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-14'), self::time('21:00')))->status);
        $own = $this->book($luca, '2026-12-14', '18:30');

        $change = ScheduleChange::removeWeekdayTime(Weekday::Monday, self::time('18:30'));
        $first = $editor->apply($this->staff, $change);
        self::assertSame(ChangeStatus::NeedsConfirmation, $first->status);
        self::assertSame((string) $monday->id, $first->plan()->fingerprint());

        $done = $editor->apply($this->staff, $change, $first->plan()->fingerprint());
        self::assertSame(ChangeStatus::Applied, $done->status);
        self::assertSame([$monday->id], array_map(static fn(CancelledBooking $cancelled): int => $cancelled->entry->booking->id, $done->cancelled));
        self::assertSame(EmailDelivery::Sent, ($done->cancelled[0] ?? self::fail())->email);
        self::assertNull($this->active($monday));
        self::assertSame(1, $this->active($tuesday));
        self::assertSame(1, $this->active($own));
        $email = $this->mailer->lastTo('anna@example.test');
        self::assertStringContainsString('dalle 18:30 alle 19:30. Quel giorno la fascia delle 18:30 non c\'è più. Ci dispiace', $email->text);
    }

    public function testClosingADayCancelsEveryBookingOfThatDayEvenOutsideTheSchedule(): void
    {
        $anna = $this->user('anna@example.test', 'Anna Bianchi');
        $booked = $this->book($anna, '2026-12-09', '07:00');
        $guest = $this->bookGuest('Marco Neri', '2026-12-09', '18:30');
        $orphan = $this->bookOutside($this->user('sara@example.test', 'Sara Neri'), '2026-12-09', '13:45');
        $other = $this->book($anna, '2026-12-10', '07:00');
        $editor = $this->editor();

        $change = ScheduleChange::close(self::day('2026-12-09'), self::day('2026-12-09'), 'ferie');
        $plan = $editor->plan($change);
        self::assertSame(self::ids($booked, $guest, $orphan), $plan->fingerprint());
        self::assertTrue($plan->closes($plan->affected[0] ?? self::fail()));

        $done = $editor->apply($this->staff, $change, $plan->fingerprint());

        self::assertSame(ChangeStatus::Applied, $done->status);
        self::assertSame([EmailDelivery::Sent, EmailDelivery::Sent, EmailDelivery::NoAddress], array_map(static fn(CancelledBooking $cancelled): EmailDelivery => $cancelled->email, $done->cancelled));
        self::assertTrue($this->schedule->day(self::day('2026-12-09'))->isClosed());
        self::assertSame('ferie', $this->schedule->day(self::day('2026-12-09'))->note);
        self::assertSame(1, $this->active($other));
        self::assertStringContainsString('Quel giorno la palestra resta chiusa.', $this->mailer->lastTo('anna@example.test')->text);
        self::assertStringNotContainsString('ferie', $this->mailer->lastTo('anna@example.test')->text, 'La nota è solo del titolare.');
    }

    public function testOnlyTheBookingsTheOwnerSawAreCancelled(): void
    {
        $anna = $this->user('anna@example.test', 'Anna Bianchi');
        $this->book($anna, '2026-12-09', '07:00');
        $editor = $this->editor();
        $change = ScheduleChange::close(self::day('2026-12-09'), self::day('2026-12-09'), null);
        $seen = $editor->plan($change)->fingerprint();

        // Fra la conferma e l'invio prenota un altro: la conferma si ripete, con lui, e nulla cambia.
        $late = $this->book($this->user('luca@example.test'), '2026-12-09', '18:30');
        $again = $editor->apply($this->staff, $change, $seen);

        self::assertSame(ChangeStatus::NeedsConfirmation, $again->status);
        self::assertStringContainsString((string) $late->id, $again->plan()->fingerprint());
        self::assertFalse($this->schedule->day(self::day('2026-12-09'))->isClosed());
        self::assertSame([], $this->mailer->sent);
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, $change, $again->plan()->fingerprint())->status);
    }

    public function testTwoChangesOneAfterTheOtherBothStay(): void
    {
        $this->book($this->user('anna@example.test'), '2026-12-07', '07:00');
        $editor = $this->editor();
        $first = ScheduleChange::removeWeekdayTime(Weekday::Monday, self::time('07:00'));
        $seen = $editor->plan($first)->fingerprint();

        // Prima della conferma, un altro invio (un altro telefono) toglie le 09:30.
        self::assertSame(ChangeStatus::Applied, $this->editor()->apply($this->staff, ScheduleChange::removeWeekdayTime(Weekday::Monday, self::time('09:30')))->status);
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, $first, $seen)->status);

        self::assertSame(['12:30', '17:30', '18:30', '20:00'], array_map(static fn(SlotTime $time): string => $time->value, $this->schedule->week()->on(Weekday::Monday)));

        // E un giorno chiuso non si riapre con un orario aggiunto da una pagina rimasta aperta.
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::close(self::day('2026-12-15'), self::day('2026-12-15'), null))->status);
        $late = $editor->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-15'), self::time('21:00')));
        self::assertSame(ChangeStatus::Refused, $late->status);
        self::assertSame('Questo giorno è chiuso: per aggiungere una fascia, prima riaprilo.', $late->refusal()->message);
    }

    /**
     * @return iterable<string, array{string, Closure(): ScheduleChange, string, bool}>
     */
    public static function refusals(): iterable
    {
        yield 'giorno passato' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::close(self::day('2026-11-24'), self::day('2026-11-24'), null), 'Martedì 24 novembre è passato: un giorno passato non si cambia.', false];
        yield 'passato prima di "c\'è già"' => ['2026-12-08 08:00:00', static fn(): ScheduleChange => ScheduleChange::addTime(self::day('2026-12-07'), self::time('18:30')), 'Lunedì 7 dicembre è passato: un giorno passato non si cambia.', false];
        yield 'prima dell\'inaugurazione' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::close(self::day('2026-12-05'), self::day('2026-12-07'), null), 'Sabato 5 dicembre è prima dell\'inaugurazione (domenica 6 dicembre): quel giorno non ci sono fasce.', false];
        yield 'prima dell\'ora d\'inaugurazione' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::addTime(self::day('2026-12-06'), self::time('10:00')), 'Le fasce cominciano dall\'inaugurazione, domenica 6 dicembre alle 15:00.', true];
        yield 'oltre la portata' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::addTime(self::day('2037-01-05'), self::time('21:00')), 'Quel giorno è troppo lontano: il pannello arriva a dieci anni da oggi.', false];
        yield 'fascia di oggi già cominciata' => ['2026-12-07 07:30:00', static fn(): ScheduleChange => ScheduleChange::removeTime(self::day('2026-12-07'), self::time('07:00')), 'La fascia delle 07:00 di oggi è già cominciata: non si toglie più.', true];
        yield 'fascia di oggi già passata' => ['2026-12-07 12:00:00', static fn(): ScheduleChange => ScheduleChange::addTime(self::day('2026-12-07'), self::time('11:00')), 'Oggi le 11:00 sono già passate: si aggiungono solo fasce che devono ancora cominciare.', true];
        yield 'chiudere oggi a giornata cominciata' => ['2026-12-07 07:30:00', static fn(): ScheduleChange => ScheduleChange::close(self::day('2026-12-07'), self::day('2026-12-08'), null), 'Oggi la fascia delle 07:00 è già cominciata: oggi non si chiude più. Chiudi da domani, oppure togli una per una le fasce di oggi che restano.', false];
        yield 'sovrapposte' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::addTime(self::day('2026-12-07'), self::time('19:00')), 'Le fasce delle 18:30 e delle 19:00 si sovrappongono: ogni fascia dura 60 minuti.', true];
        yield 'oltre la mezzanotte' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::addWeekdayTime(Weekday::Sunday, self::time('23:30')), 'La fascia delle 23:30 finirebbe dopo la mezzanotte: ogni fascia dura 60 minuti.', true];
        yield 'già nella settimana tipo' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::addWeekdayTime(Weekday::Monday, self::time('07:00')), 'Il lunedì c\'è già la fascia delle 07:00.', true];
        yield 'non nella settimana tipo' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::removeWeekdayTime(Weekday::Sunday, self::time('07:00')), 'La domenica non c\'è la fascia delle 07:00.', true];
        yield 'non nel giorno' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::removeTime(self::day('2026-12-07'), self::time('08:00')), 'La fascia delle 08:00 non c\'è.', true];
        yield 'l\'ora del cambio dell\'ora' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::addTime(self::day('2027-03-28'), self::time('02:30')), 'Domenica 28 marzo 2027 le 02:30 non esistono: è il giorno del cambio dell\'ora.', true];
        yield 'nessuna chiusura da togliere' => ['2026-11-25 08:00:00', static fn(): ScheduleChange => ScheduleChange::reopen(self::day('2026-12-07'), self::day('2026-12-09')), 'In questi giorni non c\'è nessuna chiusura da togliere.', false];
        yield 'settimana già cominciata' => ['2026-12-14 08:00:00', static fn(): ScheduleChange => ScheduleChange::copyWeek(self::day('2026-12-07')), 'La settimana dopo è già cominciata: si copia solo in una settimana che deve ancora cominciare.', false];
    }

    /**
     * @param Closure(): ScheduleChange $change
     */
    #[DataProvider('refusals')]
    public function testWhatTheScheduleDoesNotAllow(string $now, Closure $change, string $message, bool $aboutTime): void
    {
        $this->clock->moveTo($now);

        $outcome = $this->editor()->apply($this->staff, $change());

        self::assertSame(ChangeStatus::Refused, $outcome->status);
        self::assertSame($message, $outcome->refusal()->message);
        self::assertSame($aboutTime, $outcome->refusal()->aboutTime);
        self::assertSame(0, $this->rows('schedule_days') + $this->rows('schedule_weekly_slots'), 'Nulla è cambiato.');
    }

    public function testADayLikeTheWeekGoesBackToFollowingIt(): void
    {
        $editor = $this->editor();
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-09'), self::time('21:00')))->status);
        self::assertSame(DayKind::Changed, $this->schedule->day(self::day('2026-12-09'))->kind);

        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::removeTime(self::day('2026-12-09'), self::time('21:00')))->status);
        self::assertSame(DayKind::Week, $this->schedule->day(self::day('2026-12-09'))->kind);
        self::assertSame(0, $this->rows('schedule_days'), 'Nessuna eccezione che non serve.');

        self::assertSame(ChangeStatus::Unchanged, $editor->apply($this->staff, ScheduleChange::backToWeek(self::day('2026-12-09')))->status);
    }

    public function testReopeningTodayKeepsWhatHasStartedAndFollowsTheWeekForTheRest(): void
    {
        $this->clock->moveTo('2026-12-08 08:00:00');
        $editor = $this->editor();
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::close(self::day('2026-12-09'), self::day('2026-12-09'), null))->status);

        // Il giorno dopo, alle 13:00, il titolare riapre: le fasce già passate non tornano.
        $this->clock->moveTo('2026-12-09 13:00:00');
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::reopen(self::day('2026-12-09'), self::day('2026-12-09')))->status);

        self::assertSame(['17:30', '18:30', '20:00'], $this->times('2026-12-09'));
        self::assertSame(DayKind::Changed, $this->schedule->day(self::day('2026-12-09'))->kind);
    }

    public function testTheOpeningDayReopensWithTheWeekEvenIfItStartsLater(): void
    {
        $editor = $this->editor();
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::addWeekdayTime(Weekday::Sunday, self::time('10:00')))->status);
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::addWeekdayTime(Weekday::Sunday, self::time('16:00')))->status);
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::close(self::day('2026-12-06'), self::day('2026-12-06'), null))->status);

        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::reopen(self::day('2026-12-06'), self::day('2026-12-06')))->status);

        self::assertSame(DayKind::Week, $this->schedule->day(self::day('2026-12-06'))->kind);
        self::assertSame(['16:00'], array_map(static fn(ScheduledSlot $slot): string => $slot->time->value, $this->calendar()->slotsOn(self::day('2026-12-06'))), 'Le 10:00 sono prima dell\'inaugurazione.');
    }

    public function testCopyingAWeekMakesTheNextOneLookTheSame(): void
    {
        $editor = $this->editor();
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::close(self::day('2026-12-08'), self::day('2026-12-08'), 'corso'))->status);
        self::assertSame(ChangeStatus::Applied, $editor->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-09'), self::time('21:00')))->status);
        $guest = $this->bookGuest('Marco Neri', '2026-12-15', '07:00');

        $copy = ScheduleChange::copyWeek(self::day('2026-12-07'));
        $plan = $editor->plan($copy);
        self::assertSame((string) $guest->id, $plan->fingerprint(), 'Martedì 15 chiude: la sua prenotazione va disdetta.');
        self::assertSame(['2026-12-15', '2026-12-16'], array_map(static fn(DayWrite $day): string => $day->date->value, $plan->days));

        $done = $editor->apply($this->staff, $copy, $plan->fingerprint());
        self::assertSame(ChangeStatus::Applied, $done->status);
        self::assertSame([EmailDelivery::NoAddress], array_map(static fn(CancelledBooking $cancelled): EmailDelivery => $cancelled->email, $done->cancelled));
        self::assertTrue($this->schedule->day(self::day('2026-12-15'))->isClosed());
        self::assertSame('corso', $this->schedule->day(self::day('2026-12-15'))->note);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00', '21:00'], $this->times('2026-12-16'));
        self::assertSame(DayKind::Week, $this->schedule->day(self::day('2026-12-14'))->kind);
    }

    public function testACancellationStandsEvenIfTheEmailsDoNotLeaveAndAfterTwoFailuresTheRestAreNotTried(): void
    {
        $people = [];
        foreach (['a', 'b', 'c'] as $index => $name) {
            $people[] = $this->book($this->user($name . '@example.test'), '2026-12-09', ['07:00', '09:30', '12:30'][$index]);
        }
        $guest = $this->bookGuest('Marco Neri', '2026-12-09', '18:30');
        $this->mailer->failing = true;
        $editor = $this->editor();
        $change = ScheduleChange::close(self::day('2026-12-09'), self::day('2026-12-09'), null);

        $done = $editor->apply($this->staff, $change, $editor->plan($change)->fingerprint());

        self::assertSame(ChangeStatus::Applied, $done->status);
        self::assertSame([EmailDelivery::Failed, EmailDelivery::Failed, EmailDelivery::Failed, EmailDelivery::NoAddress], array_map(static fn(CancelledBooking $cancelled): EmailDelivery => $cancelled->email, $done->cancelled));
        self::assertCount(2, $this->reporter->reported, 'La terza email non si prova nemmeno.');
        foreach ([...$people, $guest] as $booking) {
            self::assertNull($this->active($booking));
        }
    }

    public function testAChangeThatLosesARaceIsDoneAgainFromTheStart(): void
    {
        $failures = [new ScheduleConflict('Gara persa.'), new PDOException('Stallo.')];
        $failures[1]->errorInfo = ['40001', 1213, 'Deadlock found'];
        $store = self::failingStore($this->schedule, $failures);

        $outcome = $this->editor($store)->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-09'), self::time('21:00')));

        self::assertSame(ChangeStatus::Applied, $outcome->status);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00', '21:00'], $this->times('2026-12-09'));

        $broken = new PDOException('Disco pieno.');
        $broken->errorInfo = ['HY000', 1021, 'Disk full'];
        $this->expectExceptionObject($broken);
        $this->editor(self::failingStore($this->schedule, [$broken]))->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-10'), self::time('21:00')));
    }

    public function testChangesCountTowardsTheirOwnGenerousLimit(): void
    {
        $editor = $this->editor();
        for ($attempt = 1; $attempt <= 150; ++$attempt) {
            $editor->apply($this->staff, ScheduleChange::backToWeek(self::day('2026-12-09')));
        }

        $throttled = $editor->apply($this->staff, ScheduleChange::addTime(self::day('2026-12-09'), self::time('21:00')));

        self::assertSame(ChangeStatus::Throttled, $throttled->status);
        self::assertSame(900, $throttled->retryAfter);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], $this->times('2026-12-09'));
    }

    /**
     * Un orario che, alle prime transazioni, lancia gli errori dati: una gara persa con un'altra richiesta.
     *
     * @param list<Throwable> $failures
     */
    private static function failingStore(PdoSchedule $inner, array $failures): ScheduleStore
    {
        return new class ($inner, $failures) implements ScheduleStore {
            /**
             * @param list<Throwable> $failures
             */
            public function __construct(
                private readonly PdoSchedule $inner,
                private array $failures,
            ) {}

            public function rules(): BookingRules
            {
                return $this->inner->rules();
            }

            public function day(LocalDate $date): DayPlan
            {
                return $this->inner->day($date);
            }

            public function week(): WeeklySchedule
            {
                return $this->inner->week();
            }

            public function isInDatabase(): bool
            {
                return $this->inner->isInDatabase();
            }

            public function weekTimesOn(LocalDate $date): array
            {
                return $this->inner->weekTimesOn($date);
            }

            public function closuresFrom(LocalDate $from): array
            {
                return $this->inner->closuresFrom($from);
            }

            public function lock(): void
            {
                $this->inner->lock();
            }

            public function transaction(Closure $work): mixed
            {
                return $this->inner->transaction(function () use ($work): mixed {
                    $result = $work();
                    $failure = array_shift($this->failures);
                    if ($failure !== null) {
                        throw $failure;
                    }

                    return $result;
                });
            }

            public function saveWeekday(Weekday $day, array $times, LocalDate $from, ?int $by, DateTimeImmutable $at): void
            {
                $this->inner->saveWeekday($day, $times, $from, $by, $at);
            }

            public function saveDay(LocalDate $date, bool $closed, array $times, ?string $note, ?int $by, DateTimeImmutable $at): void
            {
                $this->inner->saveDay($date, $closed, $times, $note, $by, $at);
            }

            public function resetDay(LocalDate $date, ?int $by, DateTimeImmutable $at): void
            {
                $this->inner->resetDay($date, $by, $at);
            }

            public function saveRules(RulesChange $rules, ?int $by, DateTimeImmutable $at): void
            {
                $this->inner->saveRules($rules, $by, $at);
            }

            public function forget(): void
            {
                $this->inner->forget();
            }
        };
    }

    private function editor(?ScheduleStore $store = null): ScheduleEditor
    {
        $store ??= $this->schedule;
        $calendar = BookingCalendar::from($store, $this->site);
        $view = new View(SiteFixture::templateDirectory(), new AssetUrls(SiteFixture::PUBLIC_DIR, SiteFixture::mtimes()), new Icons());
        $dates = new ItalianDateFormatter();

        return new ScheduleEditor(
            $calendar,
            $store,
            new PdoBookingRepository($this->database),
            new PdoUserRepository($this->database),
            new BookingNotifier(new BookingEmails(new MailTemplates($view), Origin::fromUrl('https://fourstreet.studio'), $this->site, $dates, $calendar), $this->mailer, $this->reporter),
            new RateLimiter(new PdoRateLimitStore($this->database), new Fingerprinter(str_repeat('k', 32)), $this->clock),
            $dates,
            $this->clock,
        );
    }

    private function calendar(): BookingCalendar
    {
        return BookingCalendar::from(new PdoSchedule($this->database, $this->site->booking), $this->site);
    }

    /**
     * Gli orari d'inizio di quel giorno, come li vede il sito adesso.
     *
     * @return list<string>
     */
    private function times(string $date): array
    {
        return array_map(static fn(ScheduledSlot $slot): string => $slot->time->value, $this->calendar()->slotsOn(self::day($date)));
    }

    private function book(User $customer, string $date, string $time): Booking
    {
        $slot = $this->calendar()->slot(self::day($date), self::time($time)) ?? self::fail(sprintf('Nessuna fascia il %s alle %s.', $date, $time));
        $result = (new PdoBookingRepository($this->database))->place($customer->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);
        self::assertSame(BookingStatus::Booked, $result->status);

        return $result->booking();
    }

    private function bookGuest(string $name, string $date, string $time): Booking
    {
        $slot = $this->calendar()->slot(self::day($date), self::time($time)) ?? self::fail(sprintf('Nessuna fascia il %s alle %s.', $date, $time));
        $result = (new PdoBookingRepository($this->database))->placeGuest(FullName::tryFrom($name) ?? self::fail(), $slot, 5, $this->staff->id, $this->clock->now());
        self::assertSame(BookingStatus::Booked, $result->status);

        return $result->booking();
    }

    /** Una prenotazione in una fascia che l'orario non ha: arrivata mentre il titolare cambiava l'orario. */
    private function bookOutside(User $customer, string $date, string $time): Booking
    {
        $start = self::day($date)->at(self::time($time), $this->site->timezone) ?? self::fail();
        $slot = new ScheduledSlot(self::day($date), self::time($time), $start, $this->calendar()->endOf($start));
        $result = (new PdoBookingRepository($this->database))->place($customer->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);
        self::assertSame(BookingStatus::Booked, $result->status);

        return $result->booking();
    }

    private function active(Booking $booking): ?int
    {
        return $this->database->fetchOne('SELECT active FROM bookings WHERE id = :id', ['id' => $booking->id])?->nullableInt('active');
    }

    private function rows(string $table): int
    {
        return $this->database->fetchOne('SELECT COUNT(*) AS total FROM ' . $table)?->int('total') ?? 0;
    }

    private function user(string $email, string $name = 'Cliente Prova'): User
    {
        $users = new PdoUserRepository($this->database);
        $user = $users->create(new NewUser(FullName::tryFrom($name) ?? self::fail(), EmailAddress::tryFrom($email) ?? self::fail(), 'hash', $this->clock->now(), str_repeat('0', 64)));
        $users->markEmailVerified($user, $this->clock->now());

        return $users->findById($user->id) ?? self::fail();
    }

    private static function ids(Booking ...$bookings): string
    {
        $ids = array_map(static fn(Booking $booking): int => $booking->id, $bookings);
        sort($ids);

        return implode(',', $ids);
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
