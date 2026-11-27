<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\User;
use FourStreet\Asset\AssetUrls;
use FourStreet\Booking\BookingAgenda;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingCancellation;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\BookingThrottle;
use FourStreet\Booking\CancellationResult;
use FourStreet\Booking\CancellationStatus;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\SlotBooking;
use FourStreet\Booking\SlotTime;
use FourStreet\Config\SiteConfig;
use FourStreet\Database\Database;
use FourStreet\Http\Origin;
use FourStreet\Mail\MailNotSent;
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
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * I servizi di dominio delle prenotazioni, senza HTTP: li userà così come sono anche la pagina del titolare.
 * Database dei test, posta in memoria e un orologio da spostare per le finestre di prenotazione e disdetta.
 */
#[Group('database')]
#[CoversClass(SlotBooking::class)]
#[CoversClass(BookingCancellation::class)]
#[CoversClass(BookingAgenda::class)]
#[CoversClass(BookingNotifier::class)]
#[CoversClass(BookingThrottle::class)]
#[CoversClass(BookingResult::class)]
#[CoversClass(CancellationResult::class)]
final class BookingServicesTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private InMemoryMailer $mailer;

    private CollectingReporter $reporter;

    private SiteConfig $site;

    private PdoBookingRepository $bookings;

    protected function setUp(): void
    {
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->mailer = new InMemoryMailer();
        $this->reporter = new CollectingReporter();
        $this->bookings = new PdoBookingRepository($this->database);
        $this->site = SiteFixture::config();
    }

    public function testTheCheckSaysWhetherAndWhichSeat(): void
    {
        $customer = $this->customer('mario@example.com');
        $booking = $this->booking();

        $available = $booking->check($customer, self::day('2026-12-07'), self::time('18:30'));
        self::assertSame(BookingStatus::Available, $available->status);
        self::assertSame(1, $available->seat());
        self::assertSame([], $available->seats()->takenSeats);
        self::assertSame('2026-12-07 18:30', $available->slot()->key());
        self::assertSame(0, $this->bookingCount(), 'Il controllo non prenota.');

        self::assertSame(BookingStatus::NotFound, $booking->check($customer, self::day('2026-12-07'), self::time('08:00'))->status);
        self::assertSame(BookingStatus::NotFound, $booking->check($customer, self::day('2026-12-10'), self::time('18:30'))->status, 'Oltre i giorni prenotabili.');
        self::assertSame(BookingStatus::NotFound, $booking->check($customer, self::day('2026-12-06'), self::time('18:30'))->status, 'Domenica.');
    }

    public function testTheCheckFollowsTheRulesInTheirOrder(): void
    {
        $customer = $this->customer('mario@example.com');
        $booking = $this->booking();

        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->place($this->customer($name . '@example.com'), '2026-12-07', '18:30');
        }
        self::assertSame(BookingStatus::Full, $booking->check($customer, self::day('2026-12-07'), self::time('18:30'))->status);

        $this->place($customer, '2026-12-07', '07:00');
        $already = $booking->check($customer, self::day('2026-12-07'), self::time('09:30'));
        self::assertSame(BookingStatus::AlreadyBooked, $already->status);
        self::assertSame('07:00', $already->booking()->time->value);

        $limited = $this->booking(['max_upcoming' => 1]);
        self::assertSame(BookingStatus::TooManyUpcoming, $limited->check($customer, self::day('2026-12-08'), self::time('09:30'))->status);

        $this->clock->moveTo('2026-12-07 18:30:00');
        self::assertSame(BookingStatus::Closed, $booking->check($customer, self::day('2026-12-07'), self::time('18:30'))->status, 'Chiusa vale prima di piena.');
    }

    public function testBookingSendsTheConfirmationRightAway(): void
    {
        $customer = $this->customer('mario@example.com', 'Mario Rossi');

        $result = $this->booking()->book($customer, self::day('2026-12-07'), self::time('18:30'), '203.0.113.7');

        self::assertSame(BookingStatus::Booked, $result->status);
        self::assertSame(EmailDelivery::Sent, $result->email());
        self::assertSame(1, $result->booking()->seat());
        $message = $this->mailer->lastTo('mario@example.com');
        self::assertSame('Prenotazione confermata: lunedì 7 dicembre, ore 18:30', $message->subject);
        self::assertSame('Mario Rossi', $message->to->name);
        self::assertCount(1, $message->attachments);
        // L'HTML ha gli stessi dettagli, in una tabella, e il bottone verso l'account.
        $html = (string) $message->html;
        self::assertStringContainsString('<title>Prenotazione confermata</title>', $html);
        self::assertStringContainsString('font-size:16px;line-height:20px;">Lunedì 7 dicembre 2026</td>', $html);
        self::assertStringContainsString('font-size:16px;line-height:20px;">18:30 – 19:30</td>', $html);
        self::assertStringContainsString('font-size:16px;line-height:20px;">1 di 5</td>', $html);
        self::assertStringContainsString('<a href="https://fourstreet.studio/account/"', $html);
        self::assertStringContainsString('Lunedì 7 dicembre 2026, ore 18:30: il tuo posto è prenotato.', $html);
    }

    public function testTheEmailsFollowTheCancellationRule(): void
    {
        $customer = $this->customer('mario@example.com');
        $calendar = $this->calendar(['cancel_closes_minutes_before' => 60]);
        $booking = new SlotBooking($calendar, $this->bookings, $this->notifier($calendar), $this->limiter(), $this->clock);

        $booking->book($customer, self::day('2026-12-07'), self::time('07:00'), '203.0.113.7');
        $message = $this->mailer->lastTo('mario@example.com');

        self::assertStringContainsString('disdici dal tuo account fino a un\'ora prima dell\'inizio: il posto torna libero per un altro.', $message->text);
        $ics = ($message->attachments[0] ?? self::fail('Nessun allegato.'))->content;
        self::assertStringContainsString("\r\nDESCRIPTION:Posto 1 di 5. Per disdire: https://fourstreet.studio/account/\r\n", $ics);
        self::assertStringContainsString("\r\nSUMMARY:Allenamento a 4Street\r\n", $ics);
    }

    public function testABookingStandsEvenIfTheEmailDoesNotLeave(): void
    {
        $this->mailer->failing = true;

        $result = $this->booking()->book($this->customer('mario@example.com'), self::day('2026-12-07'), self::time('18:30'), '203.0.113.7');

        self::assertSame(BookingStatus::Booked, $result->status);
        self::assertSame(EmailDelivery::Failed, $result->email());
        self::assertSame(1, $this->bookingCount());
        self::assertCount(1, $this->reporter->reported);
        self::assertInstanceOf(MailNotSent::class, $this->reporter->reported[0]);
    }

    public function testBookingClosesAtTheStart(): void
    {
        $customer = $this->customer('mario@example.com');
        $booking = $this->booking();

        $this->clock->moveTo('2026-12-07 18:29:59');
        self::assertSame(BookingStatus::Booked, $booking->book($customer, self::day('2026-12-07'), self::time('18:30'), '203.0.113.7')->status);

        $other = $this->customer('anna@example.com');
        $this->clock->moveTo('2026-12-07 18:30:00');
        $closed = $booking->book($other, self::day('2026-12-07'), self::time('18:30'), '203.0.113.7');
        self::assertSame(BookingStatus::Closed, $closed->status);
        self::assertSame('2026-12-07 18:30', $closed->slot()->key());
    }

    public function testEverySubmissionCountsAndTheThirteenthWaits(): void
    {
        $customer = $this->customer('mario@example.com');
        $booking = $this->booking();

        for ($attempt = 1; $attempt <= 12; ++$attempt) {
            self::assertNotSame(BookingStatus::Throttled, $booking->book($customer, self::day('2026-12-07'), self::time('08:00'), '203.0.113.7')->status);
        }
        $throttled = $booking->book($customer, self::day('2026-12-07'), self::time('18:30'), '203.0.113.7');

        self::assertSame(BookingStatus::Throttled, $throttled->status);
        self::assertSame(900, $throttled->retryAfter);
        self::assertSame(0, $this->bookingCount(), 'Oltre il limite non si arriva al database delle prenotazioni.');
    }

    public function testResultsRefuseImpossibleQuestions(): void
    {
        foreach ([
            static fn(): mixed => BookingResult::notFound()->slot(),
            static fn(): mixed => BookingResult::notFound()->booking(),
            static fn(): mixed => BookingResult::throttled(60)->seats(),
            static fn(): mixed => BookingResult::notFound()->email(),
            static fn(): mixed => BookingResult::notFound()->withEmail(EmailDelivery::Sent),
            static fn(): mixed => CancellationResult::notFound()->booking(),
            static fn(): mixed => CancellationResult::throttled(60)->email(),
        ] as $question) {
            try {
                $question();
                self::fail('Doveva essere un errore di programmazione.');
            } catch (LogicException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testACustomerCancelsTheirOwnActiveBookingUntilItStarts(): void
    {
        $mario = $this->customer('mario@example.com', 'Mario Rossi');
        $anna = $this->customer('anna@example.com');
        $marios = $this->place($mario, '2026-12-07', '18:30');
        $cancellation = $this->cancellation();

        self::assertNull($cancellation->cancellable($anna, $marios), 'Di un altro: non c\'è.');
        self::assertSame(CancellationStatus::NotFound, $cancellation->cancel($anna, $marios, '203.0.113.7')->status);
        self::assertNull($cancellation->cancellable($mario, PHP_INT_MAX));

        self::assertSame($marios, $cancellation->cancellable($mario, $marios)?->id);
        $result = $cancellation->cancel($mario, $marios, '203.0.113.7');
        self::assertSame(CancellationStatus::Cancelled, $result->status);
        self::assertSame(EmailDelivery::Sent, $result->email());
        self::assertSame(1, $result->booking()->seat(), 'L\'esito ha la prenotazione com\'era prima della disdetta.');
        self::assertSame('Prenotazione disdetta: lunedì 7 dicembre, ore 18:30', $this->mailer->lastTo('mario@example.com')->subject);
        self::assertSame(CancelledBy::Customer, $this->bookings->find($marios)?->cancelledBy);

        self::assertNull($cancellation->cancellable($mario, $marios), 'Già disdetta.');
        $again = $cancellation->cancel($mario, $marios, '203.0.113.7');
        self::assertSame(CancellationStatus::AlreadyCancelled, $again->status, 'Lo stesso invio di nuovo: nessun errore.');
        self::assertSame($marios, $again->booking()->id);
        self::assertSame(CancellationStatus::NotFound, $cancellation->cancel($anna, $marios, '203.0.113.7')->status, 'Per un altro resta una prenotazione che non c\'è.');
        self::assertCount(1, $this->mailer->sentTo('mario@example.com'), 'Solo l\'email della disdetta riuscita, nessuna per il secondo tentativo.');
    }

    public function testCancellingClosesAtTheConfiguredMinutesBeforeTheStart(): void
    {
        $mario = $this->customer('mario@example.com');
        $id = $this->place($mario, '2026-12-07', '18:30');
        $cancellation = $this->cancellation(['cancel_closes_minutes_before' => 120]);

        $this->clock->moveTo('2026-12-07 16:29:59');
        self::assertNotNull($cancellation->cancellable($mario, $id));
        $this->clock->moveTo('2026-12-07 16:30:00');
        self::assertNull($cancellation->cancellable($mario, $id));
        self::assertSame(CancellationStatus::NotFound, $cancellation->cancel($mario, $id, '203.0.113.7')->status);
        self::assertSame(1, $this->bookingCount());
        self::assertTrue($this->bookings->find($id)?->isActive());
    }

    public function testACancellationStandsEvenIfTheEmailDoesNotLeave(): void
    {
        $mario = $this->customer('mario@example.com');
        $id = $this->place($mario, '2026-12-07', '18:30');
        $this->mailer->failing = true;

        $result = $this->cancellation()->cancel($mario, $id, '203.0.113.7');

        self::assertSame(CancellationStatus::Cancelled, $result->status);
        self::assertSame(EmailDelivery::Failed, $result->email());
        self::assertFalse($this->bookings->find($id)?->isActive());
        self::assertCount(1, $this->reporter->reported);
    }

    public function testCancellationsCountWithBookingsTowardsTheLimit(): void
    {
        $mario = $this->customer('mario@example.com');
        $booking = $this->booking();
        for ($attempt = 1; $attempt <= 12; ++$attempt) {
            $booking->book($mario, self::day('2026-12-07'), self::time('08:00'), '203.0.113.7');
        }

        $result = $this->cancellation()->cancel($mario, 1, '203.0.113.7');

        self::assertSame(CancellationStatus::Throttled, $result->status);
        self::assertSame(900, $result->retryAfter);
    }

    public function testTheAgendaShowsWhatIsNotOverWithWhatCanStillBeCancelled(): void
    {
        $mario = $this->customer('mario@example.com');
        $anna = $this->customer('anna@example.com');
        $this->place($mario, '2026-12-07', '07:00');
        $tuesday = $this->place($mario, '2026-12-08', '18:30');
        $annas = $this->place($anna, '2026-12-08', '18:30');
        $agenda = new BookingAgenda(BookingCalendar::forSite($this->site), $this->bookings, $this->clock);

        $this->clock->moveTo('2026-12-07 07:59:59');
        $entries = $agenda->upcoming($mario);
        self::assertCount(2, $entries);
        self::assertTrue($entries[0]->inProgress);
        self::assertFalse($entries[0]->cancellable);
        self::assertSame('2026-12-07T08:00:00+01:00', $entries[0]->endsAt->format(DATE_ATOM));
        self::assertSame('2026-12-08T18:30:00+01:00', $entries[1]->startsAt->format(DATE_ATOM));
        self::assertFalse($entries[1]->inProgress);
        self::assertTrue($entries[1]->cancellable);

        $this->clock->moveTo('2026-12-07 08:00:00');
        self::assertCount(1, $agenda->upcoming($mario));

        self::assertSame($tuesday, $agenda->find($mario, $tuesday)?->id);
        self::assertNull($agenda->find($mario, $annas), 'Quella di un altro non si legge.');
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function booking(array $rules = []): SlotBooking
    {
        $calendar = $this->calendar($rules);

        return new SlotBooking($calendar, $this->bookings, $this->notifier($calendar), $this->limiter(), $this->clock);
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function cancellation(array $rules = []): BookingCancellation
    {
        $calendar = $this->calendar($rules);

        return new BookingCancellation($calendar, $this->bookings, $this->notifier($calendar), $this->limiter(), $this->clock);
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function calendar(array $rules): BookingCalendar
    {
        return BookingCalendar::forSite(SiteFixture::config(['booking' => SiteFixture::booking($rules)]));
    }

    private function notifier(BookingCalendar $calendar): BookingNotifier
    {
        $view = new View(SiteFixture::templateDirectory(), new AssetUrls(SiteFixture::PUBLIC_DIR, SiteFixture::mtimes()), new Icons());

        return new BookingNotifier(
            new BookingEmails(new MailTemplates($view), Origin::fromUrl('https://fourstreet.studio'), $this->site, new ItalianDateFormatter(), $calendar),
            $this->mailer,
            $this->reporter,
        );
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter(new PdoRateLimitStore($this->database), new Fingerprinter(str_repeat('k', 32)), $this->clock);
    }

    private function customer(string $email, string $name = 'Cliente Prova'): User
    {
        $users = new PdoUserRepository($this->database);
        $user = $users->create(new NewUser(FullName::tryFrom($name) ?? self::fail(), EmailAddress::tryFrom($email) ?? self::fail(), 'hash', $this->clock->now(), str_repeat('0', 64)));
        $users->markEmailVerified($user, $this->clock->now());

        return $users->findById($user->id) ?? self::fail();
    }

    /** Una prenotazione scritta direttamente, senza limiti né email: il suo id. */
    private function place(User $customer, string $date, string $time): int
    {
        $slot = BookingCalendar::forSite($this->site)->slot(self::day($date), self::time($time)) ?? self::fail();

        return $this->bookings->place($customer->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer)->booking()->id;
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
}
