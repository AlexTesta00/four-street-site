<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use DateTimeImmutable;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\User;
use FourStreet\Asset\AssetUrls;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\CancellationStatus;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\DayChoice;
use FourStreet\Booking\Staff\DayRoster;
use FourStreet\Booking\Staff\GymEmailRound;
use FourStreet\Booking\Staff\GymEmailSender;
use FourStreet\Booking\Staff\RosterDay;
use FourStreet\Booking\Staff\RosterSlot;
use FourStreet\Booking\Staff\SlotPhase;
use FourStreet\Booking\Staff\StaffBooking;
use FourStreet\Booking\Staff\StaffCancellation;
use FourStreet\Booking\Staff\StaffThrottle;
use FourStreet\Config\SiteConfig;
use FourStreet\Database\Database;
use FourStreet\Http\Origin;
use FourStreet\Mail\MailNotSent;
use FourStreet\Mail\MailTemplates;
use FourStreet\Security\Fingerprinter;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Tests\Support\CollectingReporter;
use FourStreet\Tests\Support\ForwardingBookings;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\MovableClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestDatabase;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Icons;
use FourStreet\View\View;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * I servizi del pannello del titolare, senza HTTP: la prenotazione e la disdetta fatte dalla palestra e il giorno
 * con chi è prenotato. Database dei test, posta in memoria e un orologio da spostare.
 */
#[Group('database')]
#[CoversClass(StaffBooking::class)]
#[CoversClass(StaffCancellation::class)]
#[CoversClass(GymEmailSender::class)]
#[CoversClass(GymEmailRound::class)]
#[CoversClass(StaffThrottle::class)]
#[CoversClass(DayRoster::class)]
#[CoversClass(RosterDay::class)]
#[CoversClass(RosterSlot::class)]
#[CoversClass(DayChoice::class)]
#[CoversClass(SlotPhase::class)]
#[CoversClass(BookingNotifier::class)]
#[CoversClass(BookingEmails::class)]
final class StaffServicesTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private InMemoryMailer $mailer;

    private CollectingReporter $reporter;

    private PdoBookingRepository $bookings;

    private User $staff;

    protected function setUp(): void
    {
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->mailer = new InMemoryMailer();
        $this->reporter = new CollectingReporter();
        $this->bookings = new PdoBookingRepository($this->database);
        $this->staff = $this->user('titolare@example.test', 'Titolare Prova');
    }

    public function testTheCheckSaysWhetherTheAdminCanStillAddSomeone(): void
    {
        $booking = $this->staffBooking();

        $available = $booking->check(self::day('2026-12-07'), self::time('18:30'));
        self::assertSame(BookingStatus::Available, $available->status);
        self::assertSame(1, $available->seat());
        self::assertSame(BookingStatus::Available, $booking->check(self::day('2026-12-28'), self::time('18:30'))->status, 'Anche oltre i giorni prenotabili.');
        self::assertSame(BookingStatus::NotFound, $booking->check(self::day('2026-12-13'), self::time('18:30'))->status, 'Domenica.');
        self::assertSame(BookingStatus::NotFound, $booking->check(self::day('2026-11-30'), self::time('18:30'))->status, 'Prima dell\'inaugurazione.');
        self::assertSame(BookingStatus::NotFound, $booking->check(self::day('2026-12-07'), self::time('08:00'))->status);

        $this->clock->moveTo('2026-12-07 18:29:59');
        self::assertSame(BookingStatus::Available, $booking->check(self::day('2026-12-07'), self::time('18:30'))->status);
        $this->clock->moveTo('2026-12-07 18:30:00');
        self::assertSame(BookingStatus::Closed, $booking->check(self::day('2026-12-07'), self::time('18:30'))->status);
    }

    public function testOnlyACustomerWhoConfirmedTheEmailIsBookedByEmail(): void
    {
        $booking = $this->staffBooking();
        $users = new PdoUserRepository($this->database);
        $users->create(new NewUser(FullName::tryFrom('In Sospeso') ?? self::fail(), self::email('sospeso@example.test'), 'hash', $this->clock->now(), str_repeat('0', 64)));

        foreach (['sospeso@example.test', 'nessuno@example.test'] as $email) {
            $unknown = $booking->bookCustomer($this->staff, self::email($email), self::day('2026-12-07'), self::time('18:30'));
            self::assertSame(BookingStatus::UnknownCustomer, $unknown->status, $email);
            self::assertSame('2026-12-07 18:30', $unknown->slot()->key());
        }
        // La fascia si guarda prima dell'email: una fascia che non c'è resta "non trovata".
        self::assertSame(BookingStatus::NotFound, $booking->bookCustomer($this->staff, self::email('nessuno@example.test'), self::day('2026-12-13'), self::time('18:30'))->status);
        self::assertSame(0, $this->bookingCount());
        self::assertSame([], $this->mailer->sent);
    }

    public function testTheLimitCountsBeforeTheEmailIsLookedUpSoItTellsNothingAboutAccounts(): void
    {
        $booking = $this->staffBooking();
        $this->user('anna@example.test', 'Anna Bianchi');
        for ($attempt = 1; $attempt <= 60; ++$attempt) {
            self::assertSame(BookingStatus::UnknownCustomer, $booking->bookCustomer($this->staff, self::email('nessuno' . $attempt . '@example.test'), self::day('2026-12-07'), self::time('18:30'))->status);
        }

        // Oltre il limite un'email nota e una sconosciuta hanno la stessa risposta, e nessuna prenotazione.
        $known = $booking->bookCustomer($this->staff, self::email('anna@example.test'), self::day('2026-12-07'), self::time('18:30'));
        $unknown = $booking->bookCustomer($this->staff, self::email('nessuno@example.test'), self::day('2026-12-07'), self::time('18:30'));
        self::assertSame(BookingStatus::Throttled, $known->status);
        self::assertSame(BookingStatus::Throttled, $unknown->status);
        self::assertSame($known->retryAfter, $unknown->retryAfter);
        self::assertSame(0, $this->bookingCount());
    }

    public function testACustomerBookedByTheGymGetsTheSameConfirmationAndNoLimitOfUpcomingBookings(): void
    {
        $anna = $this->user('anna@example.test', 'Anna Bianchi');
        $booking = $this->staffBooking(['max_upcoming' => 1]);
        $this->place($anna, '2026-12-08', '07:00');

        $result = $booking->bookCustomer($this->staff, self::email('anna@example.test'), self::day('2026-12-07'), self::time('18:30'));

        self::assertSame(BookingStatus::Booked, $result->status);
        self::assertSame(EmailDelivery::Sent, $result->email());
        self::assertSame(CreatedBy::Admin, $result->booking()->createdBy);
        self::assertSame($anna->id, $result->booking()->userId);
        $message = $this->mailer->lastTo('anna@example.test');
        self::assertSame('Prenotazione confermata: lunedì 7 dicembre, ore 18:30', $message->subject);
        self::assertCount(1, $message->attachments);

        // Una al giorno resta: lo stesso invio è una ripetizione, un'altra fascia quel giorno no.
        self::assertTrue($booking->bookCustomer($this->staff, self::email('anna@example.test'), self::day('2026-12-07'), self::time('18:30'))->isRepeat());
        $other = $booking->bookCustomer($this->staff, self::email('anna@example.test'), self::day('2026-12-07'), self::time('07:00'));
        self::assertSame(BookingStatus::AlreadyBooked, $other->status);
        self::assertFalse($other->isRepeat());
        self::assertCount(1, $this->mailer->sentTo('anna@example.test'));
    }

    public function testSomeoneWithoutAnAccountIsBookedWithoutEmail(): void
    {
        $result = $this->staffBooking()->bookGuest($this->staff, self::person('Marco Neri'), self::day('2026-12-07'), self::time('18:30'));

        self::assertSame(BookingStatus::Booked, $result->status);
        self::assertSame(EmailDelivery::NoAddress, $result->email());
        self::assertSame('Marco Neri', $result->booking()->guestName);
        self::assertSame([], $this->mailer->sent);
        self::assertTrue($this->staffBooking()->bookGuest($this->staff, self::person('Marco Neri'), self::day('2026-12-07'), self::time('18:30'))->isRepeat());
    }

    public function testTheSeatsAndTheStartHoldForTheGymToo(): void
    {
        $booking = $this->staffBooking(['booking_closes_minutes_before' => 120]);
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->place($this->user($name . '@example.test'), '2026-12-07', '18:30');
        }
        self::assertSame(BookingStatus::Full, $booking->bookGuest($this->staff, self::person('Sesto Ospite'), self::day('2026-12-07'), self::time('18:30'))->status);
        self::assertSame(BookingStatus::Full, $booking->check(self::day('2026-12-07'), self::time('18:30'))->status);

        // La chiusura anticipata dei clienti non vale per il titolare; l'inizio sì.
        $this->clock->moveTo('2026-12-07 19:00:00');
        self::assertSame(BookingStatus::Booked, $booking->bookGuest($this->staff, self::person('Ospite Serale'), self::day('2026-12-07'), self::time('20:00'))->status);
        $closed = $booking->bookGuest($this->staff, self::person('Ospite Tardi'), self::day('2026-12-07'), self::time('18:30'));
        self::assertSame(BookingStatus::Closed, $closed->status);
        self::assertSame('2026-12-07 18:30', $closed->slot()->key());
        self::assertSame(BookingStatus::NotFound, $booking->bookGuest($this->staff, self::person('Ospite Prova'), self::day('2026-12-13'), self::time('18:30'))->status);
    }

    public function testEverySubmissionOfTheAdminCountsAndTheSixtyFirstWaits(): void
    {
        $booking = $this->staffBooking();
        $cancellation = $this->staffCancellation();
        for ($attempt = 1; $attempt <= 59; ++$attempt) {
            self::assertNotSame(BookingStatus::Throttled, $booking->bookGuest($this->staff, self::person('Ospite ' . $attempt), self::day('2026-12-07'), self::time('08:00'))->status);
        }
        self::assertNotSame(CancellationStatus::Throttled, $cancellation->cancel($this->staff, PHP_INT_MAX)->status);

        $throttled = $booking->bookGuest($this->staff, self::person('Ospite Prova'), self::day('2026-12-07'), self::time('18:30'));
        self::assertSame(BookingStatus::Throttled, $throttled->status);
        self::assertSame(900, $throttled->retryAfter);
        self::assertSame(CancellationStatus::Throttled, $cancellation->cancel($this->staff, 1)->status);
        self::assertSame(0, $this->bookingCount(), 'Oltre il limite non si arriva al database delle prenotazioni.');

        // Il limite è del titolare: un altro titolare, e i clienti, non lo sentono.
        self::assertSame(BookingStatus::Booked, $booking->bookGuest($this->user('socio@example.test'), self::person('Ospite Prova'), self::day('2026-12-07'), self::time('18:30'))->status);
        self::assertSame('pannello-account', StaffThrottle::ChangesPerAccount->limit()->name);
    }

    public function testTheGymCancelsUntilTheStartAndTellsTheCustomer(): void
    {
        $anna = $this->user('anna@example.test', 'Anna Bianchi');
        $id = $this->place($anna, '2026-12-07', '18:30');
        $cancellation = $this->staffCancellation(['cancel_closes_minutes_before' => 120]);

        $this->clock->moveTo('2026-12-07 18:29:59');
        $entry = $cancellation->cancellable($id);
        self::assertSame('Anna Bianchi', $entry?->name);
        self::assertSame('anna@example.test', $entry->email?->value);

        $result = $cancellation->cancel($this->staff, $id);
        self::assertSame(CancellationStatus::Cancelled, $result->status);
        self::assertSame(EmailDelivery::Sent, $result->email());
        self::assertSame(1, $result->booking()->seat(), 'L\'esito ha la prenotazione com\'era prima della disdetta.');
        $cancelled = $this->bookings->find($id) ?? self::fail();
        self::assertSame(CancelledBy::Admin, $cancelled->cancelledBy);
        self::assertSame(GymEmail::Sent, $cancelled->gymEmail, 'L\'esito dell\'email resta nel database.');
        self::assertSame('Prenotazione disdetta dalla palestra: lunedì 7 dicembre, ore 18:30', $this->mailer->lastTo('anna@example.test')->subject);
        self::assertStringContainsString('dalle 18:30 alle 19:30. Ci dispiace per il cambio di programma.', $this->mailer->lastTo('anna@example.test')->text, 'Nessun perché, come sempre per una disdetta sola.');

        self::assertNull($cancellation->cancellable($id), 'Già disdetta.');
        self::assertSame(CancellationStatus::AlreadyCancelled, $cancellation->cancel($this->staff, $id)->status);
        self::assertCount(1, $this->mailer->sentTo('anna@example.test'));
    }

    public function testAGuestIsCancelledWithoutEmailAndNothingCancelsOnceStarted(): void
    {
        $guest = $this->bookings->placeGuest(self::person('Marco Neri'), $this->slot('2026-12-07', '18:30'), 5, $this->staff->id, $this->clock->now())->booking();
        $started = $this->place($this->user('anna@example.test'), '2026-12-07', '07:00');
        $cancellation = $this->staffCancellation();

        $this->clock->moveTo('2026-12-07 07:00:00');
        self::assertNull($cancellation->cancellable($started));
        self::assertSame(CancellationStatus::NotFound, $cancellation->cancel($this->staff, $started)->status);
        self::assertTrue($this->bookings->find($started)?->isActive());
        self::assertSame(CancellationStatus::NotFound, $cancellation->cancel($this->staff, PHP_INT_MAX)->status);

        $result = $cancellation->cancel($this->staff, $guest->id);
        self::assertSame(CancellationStatus::Cancelled, $result->status);
        self::assertSame(EmailDelivery::NoAddress, $result->email());
        self::assertSame([], $this->mailer->sent);
        self::assertNull($this->bookings->find($guest->id)?->gymEmail, 'Chi non ha l\'account non ha un\'email, né uno stato.');
    }

    public function testACancellationStandsEvenIfTheEmailDoesNotLeave(): void
    {
        $id = $this->place($this->user('anna@example.test'), '2026-12-07', '18:30');
        $this->mailer->failing = true;

        $result = $this->staffCancellation()->cancel($this->staff, $id);

        self::assertSame(CancellationStatus::Cancelled, $result->status);
        self::assertSame(EmailDelivery::Failed, $result->email());
        $cancelled = $this->bookings->find($id) ?? self::fail();
        self::assertFalse($cancelled->isActive());
        self::assertSame(GymEmail::Failed, $cancelled->gymEmail, 'Non partita: la pagina Disdette la rimanda.');
        self::assertCount(1, $this->reporter->reported);
        self::assertInstanceOf(MailNotSent::class, $this->reporter->reported[0]);
    }

    public function testAnEmailTakenAtTheSameMomentByAnotherSendIsLeftToIt(): void
    {
        $id = $this->place($this->user('anna@example.test'), '2026-12-07', '18:30');
        // Appena scritta la disdetta (in attesa), "Invia le email rimaste" da un altro telefono prende l'email per primo.
        $bookings = new class ($this->bookings) extends ForwardingBookings {
            public function cancel(int $id, DateTimeImmutable $at, CancelledBy $by): bool
            {
                $cancelled = parent::cancel($id, $at, $by);
                Assert::assertTrue($this->inner->claimGymEmail($id));

                return $cancelled;
            }
        };
        $cancellation = new StaffCancellation($bookings, $this->sender($this->calendar([]), $bookings), $this->limiter(), $this->clock);

        $result = $cancellation->cancel($this->staff, $id);

        self::assertSame(CancellationStatus::Cancelled, $result->status);
        self::assertSame(EmailDelivery::Pending, $result->email(), 'Non è partita da qui: la manda l\'altro invio.');
        self::assertSame([], $this->mailer->sent, 'Mai due email per la stessa disdetta.');
        self::assertSame(GymEmail::Sending, $this->bookings->find($id)?->gymEmail);
    }

    public function testTheDayOfTheGymWithItsSlotsAndWhatCanBeDone(): void
    {
        $this->place($this->user('anna@example.test', 'Anna Bianchi'), '2026-12-07', '07:00');
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->place($this->user($name . '@example.test'), '2026-12-07', '18:30');
        }
        $roster = $this->roster();

        $day = $this->rosterOn($roster, '2026-12-07');
        self::assertSame('2026-12-07', $day->date->value);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], array_map(static fn(RosterSlot $slot): string => $slot->slot->time->value, $day->slots));
        self::assertCount(6, $day->slots);
        self::assertSame(1, $day->slots[0]->taken());
        self::assertTrue($day->slots[0]->canAdd());
        self::assertTrue($day->slots[0]->canCancel());
        self::assertSame([1], $day->slots[0]->seats()->takenSeats);
        self::assertNull($day->slots[0]->seats()->nextSeat(), 'Nessun posto segnato come il prossimo.');
        self::assertTrue($day->slots[4]->isFull());
        self::assertFalse($day->slots[4]->canAdd());
        self::assertTrue($day->slots[4]->canCancel());
        self::assertSame('2026-12-06', $day->previous?->value);
        self::assertSame('2026-12-08', $day->next?->value);
        self::assertFalse($day->isToday());
        self::assertFalse($day->isPast());
        self::assertFalse($day->isBeforeOpening());
        self::assertSame(
            [['2026-12-06', false], ['2026-12-07', true], ['2026-12-08', true], ['2026-12-09', true]],
            array_map(static fn(DayChoice $choice): array => [$choice->date->value, $choice->hasSlots], $day->choices),
        );

        // Lunedì alle 18:45: le 07:00 sono finite, le 18:30 in corso, le 20:00 ancora da venire.
        $this->clock->moveTo('2026-12-07 18:45:00');
        $evening = $this->rosterOn($roster, null);
        self::assertTrue($evening->isToday());
        self::assertSame([SlotPhase::Over, SlotPhase::Over, SlotPhase::Over, SlotPhase::Over, SlotPhase::InProgress, SlotPhase::Upcoming], array_map(static fn(RosterSlot $slot): SlotPhase => $slot->phase, $evening->slots));
        self::assertCount(6, $evening->slots);
        self::assertFalse($evening->slots[4]->canCancel());
        self::assertFalse($evening->slots[0]->canAdd());

        $this->clock->moveTo('2026-12-08 09:00:00');
        self::assertTrue($this->rosterOn($roster, '2026-12-07')->isPast());
        self::assertTrue($this->rosterOn($roster, '2026-11-30')->isBeforeOpening());
        self::assertSame([], $this->rosterOn($roster, '2026-12-13')->slots);
    }

    public function testWithoutADayTheRosterOpensTodayOrTheFirstDayWithSlots(): void
    {
        $roster = $this->roster();

        self::assertSame('2026-12-07', $this->rosterOn($roster, null)->date->value, 'Prima dell\'inaugurazione: il primo giorno con le fasce.');
        $this->clock->moveTo('2026-12-13 10:00:00');
        self::assertSame('2026-12-14', $this->rosterOn($roster, null)->date->value, 'Domenica: il giorno dopo.');
        $this->clock->moveTo('2026-12-14 23:00:00');
        self::assertSame('2026-12-14', $this->rosterOn($roster, null)->date->value, 'Oggi, anche a fasce finite.');

        // Senza giorni prenotabili con delle fasce (inaugurazione lontana): oggi.
        $far = new DayRoster(BookingCalendar::forSite(SiteFixture::config(['opening' => '2027-03-01 09:00'])), $this->bookings, $this->clock);
        self::assertSame('2026-12-14', $this->rosterOn($far, null)->date->value);
        self::assertSame([], $this->rosterOn($far, null)->choices);
    }

    public function testThePanelReachesTenYearsBackAndAheadAndNeverPastTheCalendar(): void
    {
        $roster = $this->roster();

        // Oggi è il 25 novembre 2026: dal 1° gennaio 2016 al 31 dicembre 2036, con il giorno prima e dopo sempre veri.
        $first = $this->rosterOn($roster, '2016-01-01');
        self::assertNull($first->previous);
        self::assertSame('2016-01-02', $first->next?->value);
        $last = $this->rosterOn($roster, '2036-12-31');
        self::assertSame('2036-12-30', $last->previous?->value);
        self::assertNull($last->next);
        foreach (['2015-12-31', '2037-01-01', '0000-01-01', '9999-12-31'] as $outside) {
            self::assertNull($roster->day(self::day($outside)), $outside);
        }

        // Oltre la portata il titolare non prenota nemmeno: la fascia esiste nel calendario, ma per lui non c'è.
        self::assertSame(BookingStatus::Available, $this->staffBooking()->check(self::day('2036-12-29'), self::time('18:30'))->status);
        self::assertSame(BookingStatus::NotFound, $this->staffBooking()->check(self::day('2037-01-05'), self::time('18:30'))->status);
    }

    public function testBookingsInASlotThatTheScheduleNoLongerHasStayInTheirSlot(): void
    {
        $this->place($this->user('anna@example.test', 'Anna Bianchi'), '2026-12-07', '12:30');
        $week = SiteFixture::week();
        $week['lunedì'] = ['07:00', '18:30'];
        $roster = new DayRoster(BookingCalendar::forSite(SiteFixture::config(['booking' => SiteFixture::booking(['schedule' => $week])])), $this->bookings, $this->clock);

        $slots = $this->rosterOn($roster, '2026-12-07')->slots;

        self::assertSame(['07:00', '12:30', '18:30'], array_map(static fn(RosterSlot $slot): string => $slot->slot->time->value, $slots));
        self::assertCount(3, $slots);
        self::assertCount(1, $slots[1]->entries);
        self::assertFalse($slots[1]->scheduled);
        self::assertSame('13:30', $slots[1]->slot->endsAt->format('H:i'));
        self::assertSame('Anna Bianchi', $slots[1]->entries[0]->name);
        self::assertTrue($slots[1]->canCancel());
        self::assertFalse($slots[1]->canAdd(), 'Lì non si aggiunge nessuno.');
        self::assertSame('Anna Bianchi', $roster->entry($slots[1]->entries[0]->booking->id)?->name);
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function staffBooking(array $rules = []): StaffBooking
    {
        $calendar = $this->calendar($rules);

        return new StaffBooking($calendar, $this->bookings, new PdoUserRepository($this->database), $this->notifier($calendar), $this->limiter(), $this->clock);
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function staffCancellation(array $rules = []): StaffCancellation
    {
        return new StaffCancellation($this->bookings, $this->sender($this->calendar($rules)), $this->limiter(), $this->clock);
    }

    /** Le email della palestra di una richiesta, come le collega AdminModule. */
    private function sender(BookingCalendar $calendar, ?BookingRepository $bookings = null): GymEmailSender
    {
        return new GymEmailSender($bookings ?? $this->bookings, new PdoUserRepository($this->database), $this->emails($calendar), $this->notifier($calendar), $this->clock);
    }

    private function roster(): DayRoster
    {
        return new DayRoster($this->calendar([]), $this->bookings, $this->clock);
    }

    /** Il giorno del pannello, che deve essere a portata. */
    private function rosterOn(DayRoster $roster, ?string $date): RosterDay
    {
        return $roster->day($date === null ? null : self::day($date)) ?? self::fail('Giorno fuori dalla portata del pannello.');
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function calendar(array $rules): BookingCalendar
    {
        return BookingCalendar::forSite($this->site($rules));
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function site(array $rules): SiteConfig
    {
        return SiteFixture::config(['booking' => SiteFixture::booking($rules)]);
    }

    private function notifier(BookingCalendar $calendar): BookingNotifier
    {
        return new BookingNotifier($this->emails($calendar), $this->mailer, $this->reporter);
    }

    private function emails(BookingCalendar $calendar): BookingEmails
    {
        $view = new View(SiteFixture::templateDirectory(), new AssetUrls(SiteFixture::PUBLIC_DIR, SiteFixture::mtimes()), new Icons());

        return new BookingEmails(new MailTemplates($view), Origin::fromUrl('https://fourstreet.studio'), $this->site([]), new ItalianDateFormatter(), $calendar);
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter(new PdoRateLimitStore($this->database), new Fingerprinter(str_repeat('k', 32)), $this->clock);
    }

    private function user(string $email, string $name = 'Cliente Prova'): User
    {
        $users = new PdoUserRepository($this->database);
        $user = $users->create(new NewUser(self::person($name), self::email($email), 'hash', $this->clock->now(), str_repeat('0', 64)));
        $users->markEmailVerified($user, $this->clock->now());

        return $users->findById($user->id) ?? self::fail();
    }

    /** Una prenotazione del cliente scritta direttamente, senza limiti né email: il suo id. */
    private function place(User $customer, string $date, string $time): int
    {
        return $this->bookings->place($customer->id, $this->slot($date, $time), 5, null, $this->clock->now(), CreatedBy::Customer)->booking()->id;
    }

    private function slot(string $date, string $time): ScheduledSlot
    {
        return $this->calendar([])->slot(self::day($date), self::time($time)) ?? self::fail();
    }

    private function bookingCount(): int
    {
        return $this->database->fetchOne('SELECT COUNT(*) AS total FROM bookings')?->int('total') ?? 0;
    }

    private static function day(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail();
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? self::fail();
    }

    private static function person(string $value): FullName
    {
        return FullName::tryFrom($value) ?? self::fail();
    }

    private static function email(string $value): EmailAddress
    {
        return EmailAddress::tryFrom($value) ?? self::fail();
    }
}
