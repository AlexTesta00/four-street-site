<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\User;
use FourStreet\Account\UserRepository;
use FourStreet\Asset\AssetUrls;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\CancellationDay;
use FourStreet\Booking\Staff\CancellationSheet;
use FourStreet\Booking\Staff\GymCancellations;
use FourStreet\Booking\Staff\GymEmailRound;
use FourStreet\Booking\Staff\GymEmailSender;
use FourStreet\Booking\Staff\GymEmailStop;
use FourStreet\Booking\Staff\ResendOutcome;
use FourStreet\Booking\Staff\ResendStatus;
use FourStreet\Booking\Staff\StaffThrottle;
use FourStreet\Config\SiteConfig;
use FourStreet\Database\Database;
use FourStreet\Http\Origin;
use FourStreet\Mail\Mailer;
use FourStreet\Mail\MailTemplates;
use FourStreet\Security\Fingerprinter;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Tests\Support\CollectingReporter;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\MovableClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\SlowMailer;
use FourStreet\Tests\Support\TestDatabase;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Icons;
use FourStreet\View\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Le disdette della palestra per la pagina Disdette, senza HTTP: l'elenco con com'è andata l'email di ognuno, e "Invia
 * le email rimaste" con il perché dell'orario di adesso, il tetto di tempo, i due invii falliti di fila, il limite dei
 * tentativi e due invii del modulo nello stesso istante. Database dei test, posta in memoria (o lenta) e un orologio
 * da spostare, che parte da mercoledì 25 novembre 2026, 08:00.
 */
#[Group('database')]
#[CoversClass(GymCancellations::class)]
#[CoversClass(CancellationSheet::class)]
#[CoversClass(CancellationDay::class)]
#[CoversClass(ResendOutcome::class)]
#[CoversClass(ResendStatus::class)]
#[CoversClass(GymEmailSender::class)]
#[CoversClass(GymEmailRound::class)]
#[CoversClass(GymEmailStop::class)]
#[CoversClass(GymEmail::class)]
final class GymCancellationsTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private InMemoryMailer $mailer;

    private CollectingReporter $reporter;

    private SiteConfig $site;

    private PdoBookingRepository $bookings;

    private User $staff;

    protected function setUp(): void
    {
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->mailer = new InMemoryMailer();
        $this->reporter = new CollectingReporter();
        $this->site = SiteFixture::config();
        $this->bookings = new PdoBookingRepository($this->database);
        $this->staff = $this->user('titolare@example.test', 'Titolare Prova');
    }

    public function testThePageHasTheGymsCancellationsByDayWithTheirEmail(): void
    {
        $sent = $this->cancelled($this->user('anna@example.test', 'Anna Bianchi'), '2026-12-09', '18:30', GymEmail::Sent);
        $pending = $this->cancelled($this->user('luca@example.test', 'Luca Verdi'), '2026-12-07', '18:30');
        $failed = $this->cancelled($this->user('sara@example.test', 'Sara Neri'), '2026-12-07', '07:00', GymEmail::Failed);
        $guest = $this->bookings->placeGuest(self::person('Marco Neri'), $this->calendar()->slot(self::day('2026-12-09'), self::time('07:00')) ?? self::fail(), 5, $this->staff->id, $this->clock->now())->booking();
        $this->bookings->cancel($guest->id, $this->clock->now(), CancelledBy::Admin);
        // Disdetta dalla palestra prima che il sito registrasse l'email: resta senza esito, nessuno lo inventa.
        $before = $this->cancelled($this->user('bea@example.test', 'Bea Gialli'), '2026-12-10', '07:00');
        $this->database->execute('UPDATE bookings SET gym_email = NULL WHERE id = :id', ['id' => $before->id]);
        // Non ci sono: una disdetta del cliente, e una prenotazione attiva.
        $byCustomer = $this->place($this->user('gio@example.test'), '2026-12-08', '07:00');
        $this->bookings->cancel($byCustomer->id, $this->clock->now(), CancelledBy::Customer);
        $this->place($this->user('ada@example.test'), '2026-12-08', '09:30');

        $sheet = $this->cancellations()->upcoming();

        self::assertSame('2026-11-25', $sheet->today->value);
        self::assertSame(
            [['2026-12-07', [$failed->id, $pending->id]], ['2026-12-09', [$guest->id, $sent->id]], ['2026-12-10', [$before->id]]],
            array_map(static fn(CancellationDay $day): array => [$day->date->value, array_map(static fn(RosterEntry $entry): int => $entry->booking->id, $day->entries)], $sheet->days),
        );
        self::assertSame(5, $sheet->count());
        self::assertSame(2, $sheet->toSend(), 'Da mandare: in attesa e non partite, mai quelle senza esito.');

        // Cominciata la fascia, la disdetta esce dalla pagina: non c'è più nessuno da avvisare in tempo.
        $this->clock->moveTo('2026-12-07 18:30:00');
        $later = $this->cancellations()->upcoming();
        self::assertSame(['2026-12-09', '2026-12-10'], array_map(static fn(CancellationDay $day): string => $day->date->value, $later->days));
        self::assertSame(0, $later->toSend());
    }

    public function testThePageReadsTheDatabaseOnce(): void
    {
        // Con l'orario del file e un repository che conta: una lettura sola, con le persone e le email.
        $bookings = $this->createMock(BookingRepository::class);
        $bookings->expects(self::once())
            ->method('upcomingGymCancellations')
            ->with(self::day('2026-11-25'), $this->clock->now())
            ->willReturn([]);
        $bookings->expects(self::never())->method(self::logicalNot(self::identicalTo('upcomingGymCancellations')));
        $calendar = BookingCalendar::forSite($this->site);
        $sender = new GymEmailSender($bookings, new PdoUserRepository($this->database), $this->emails($calendar), $this->notifier($calendar, $this->mailer), $this->clock);

        $sheet = (new GymCancellations($calendar, $bookings, $sender, $this->limiter(), $this->clock))->upcoming();

        self::assertSame([], $sheet->days);
        self::assertSame(0, $sheet->count());
    }

    public function testTheRemainingEmailsLeaveWithTheReasonOfTheScheduleAsItIsNow(): void
    {
        $closed = $this->cancelled($this->user('anna@example.test', 'Anna Bianchi'), '2026-12-09', '07:00');
        $removed = $this->cancelled($this->user('luca@example.test', 'Luca Verdi'), '2026-12-10', '18:30', GymEmail::Failed);
        $single = $this->cancelled($this->user('sara@example.test', 'Sara Neri'), '2026-12-11', '12:30');
        $sent = $this->cancelled($this->user('bea@example.test', 'Bea Gialli'), '2026-12-12', '07:00', GymEmail::Sent);
        $sending = $this->cancelled($this->user('gio@example.test', 'Gio Rossi'), '2026-12-14', '07:00', GymEmail::Sending);
        // L'orario di adesso: il 9 chiuso, il 10 senza le 18:30; l'11 com'era (la disdetta di una prenotazione sola).
        $schedule = new PdoSchedule($this->database, $this->site->booking);
        $schedule->saveDay(self::day('2026-12-09'), true, [], 'ferie', $this->staff->id, $this->clock->now());
        $schedule->saveDay(self::day('2026-12-10'), false, array_map(self::time(...), ['07:00', '09:30', '12:30', '17:30', '20:00']), null, $this->staff->id, $this->clock->now());

        $outcome = $this->cancellations()->resend($this->staff);

        self::assertSame(ResendStatus::Sent, $outcome->status);
        self::assertSame(3, $outcome->sent);
        self::assertNull($outcome->stopped);
        self::assertSame(['sent', 'sent', 'sent', 'sent', 'sending'], array_map($this->gymEmail(...), [$closed, $removed, $single, $sent, $sending]));
        self::assertStringContainsString('dalle 07:00 alle 08:00. Quel giorno la palestra resta chiusa. Ci dispiace', $this->mailer->lastTo('anna@example.test')->text);
        self::assertStringNotContainsString('ferie', $this->mailer->lastTo('anna@example.test')->text, 'La nota è solo del titolare.');
        self::assertStringContainsString('dalle 18:30 alle 19:30. Quel giorno la fascia delle 18:30 non c\'è più. Ci dispiace', $this->mailer->lastTo('luca@example.test')->text);
        self::assertStringContainsString('dalle 12:30 alle 13:30. Ci dispiace per il cambio di programma.', $this->mailer->lastTo('sara@example.test')->text);
        // Una partita non si rimanda; una in invio (forse partita, forse no) nemmeno: mai due email per la stessa disdetta.
        self::assertSame([], $this->mailer->sentTo('bea@example.test'));
        self::assertSame([], $this->mailer->sentTo('gio@example.test'));
        self::assertCount(3, $this->mailer->sent);
        self::assertSame(0, $this->cancellations()->upcoming()->toSend());
    }

    public function testWithNothingToSendNothingLeaves(): void
    {
        $this->cancelled($this->user('anna@example.test'), '2026-12-09', '07:00', GymEmail::Sent);
        $guest = $this->bookings->placeGuest(self::person('Marco Neri'), $this->calendar()->slot(self::day('2026-12-09'), self::time('09:30')) ?? self::fail(), 5, $this->staff->id, $this->clock->now())->booking();
        $this->bookings->cancel($guest->id, $this->clock->now(), CancelledBy::Admin);

        $outcome = $this->cancellations()->resend($this->staff);

        self::assertSame(ResendStatus::NothingToSend, $outcome->status);
        self::assertSame(0, $outcome->sent);
        self::assertSame([], $this->mailer->sent);
    }

    public function testTheSendsStopAtTheTimeLimitAndTheNextPressSendsTheRest(): void
    {
        $people = [];
        foreach (['07:00', '09:30', '12:30', '17:30', '18:30'] as $index => $time) {
            $people[] = $this->cancelled($this->user('cliente' . $index . '@example.test'), '2026-12-09', $time);
        }
        // Ogni invio dura 5 secondi: si comincia a 0, 5 e 10 secondi; a 15 il tempo (12 secondi) è finito.
        $slow = new SlowMailer($this->mailer, $this->clock, 5);

        $first = $this->cancellations($slow)->resend($this->staff);

        self::assertSame(ResendStatus::Sent, $first->status);
        self::assertSame(3, $first->sent);
        self::assertSame(GymEmailStop::TimeUp, $first->stopped);
        self::assertSame(['sent', 'sent', 'sent', 'pending', 'pending'], array_map($this->gymEmail(...), $people));

        // Premuto di nuovo (una richiesta nuova, con il suo tempo): partono le altre.
        $second = $this->cancellations($slow)->resend($this->staff);
        self::assertSame(2, $second->sent);
        self::assertNull($second->stopped);
        self::assertSame(['sent', 'sent', 'sent', 'sent', 'sent'], array_map($this->gymEmail(...), $people));
        self::assertCount(5, $this->mailer->sent);
    }

    public function testAfterTwoFailuresInARowTheRestStayAsTheyWere(): void
    {
        $people = [];
        foreach (['07:00', '09:30', '12:30'] as $index => $time) {
            $people[] = $this->cancelled($this->user('cliente' . $index . '@example.test'), '2026-12-09', $time, $index === 2 ? GymEmail::Failed : GymEmail::Pending);
        }
        $this->mailer->failing = true;

        $outcome = $this->cancellations()->resend($this->staff);

        self::assertSame(0, $outcome->sent);
        self::assertSame(GymEmailStop::MailDown, $outcome->stopped);
        self::assertSame(['failed', 'failed', 'failed'], array_map($this->gymEmail(...), $people), 'La terza non si prova: resta com\'era.');
        self::assertCount(2, $this->reporter->reported);
    }

    public function testTheEmailsNeverTriedGoFirstSoTwoRefusedAddressesDoNotHoldBackTheOthers(): void
    {
        // Nelle prime fasce due indirizzi che il server di posta rifiuta ogni volta, poi altri due.
        $refused = [
            $this->cancelled($this->user('rifiutata1@example.test'), '2026-12-09', '07:00'),
            $this->cancelled($this->user('rifiutata2@example.test'), '2026-12-09', '09:30'),
        ];
        $others = [
            $this->cancelled($this->user('anna@example.test'), '2026-12-09', '12:30'),
            $this->cancelled($this->user('luca@example.test'), '2026-12-09', '17:30'),
        ];
        $this->mailer->unreachable = ['rifiutata1@example.test', 'rifiutata2@example.test'];

        // La prima volta le rifiutate fermano il giro: due invii falliti di fila.
        $first = $this->cancellations()->resend($this->staff);
        self::assertSame(0, $first->sent);
        self::assertSame(GymEmailStop::MailDown, $first->stopped);
        self::assertSame(['pending', 'pending'], array_map($this->gymEmail(...), $others));

        // Premuto di nuovo, prima le mai provate e poi le non partite: le altre partono, le rifiutate restano da avvisare.
        $second = $this->cancellations()->resend($this->staff);
        self::assertSame(2, $second->sent);
        self::assertNull($second->stopped);
        self::assertSame(['sent', 'sent'], array_map($this->gymEmail(...), $others));
        self::assertSame(['failed', 'failed'], array_map($this->gymEmail(...), $refused));
        self::assertCount(1, $this->mailer->sentTo('anna@example.test'));
        self::assertCount(1, $this->mailer->sentTo('luca@example.test'));
    }

    public function testAnAccountGoneMeanwhileIsNobodyToWarnAndNoMailFailure(): void
    {
        $people = [];
        foreach (['07:00', '09:30', '12:30'] as $index => $time) {
            $people[] = $this->cancelled($this->user('cliente' . $index . '@example.test'), '2026-12-09', $time);
        }
        // L'account non si trova più (cancellato nello stesso istante): nessuno da avvisare, e non è il server di posta
        // che non risponde, quindi non ferma il giro; le righe restano com'erano.
        $users = self::createStub(UserRepository::class);
        $users->method('findById')->willReturn(null);

        $outcome = $this->cancellations(users: $users)->resend($this->staff);

        self::assertSame(ResendStatus::Sent, $outcome->status);
        self::assertSame(0, $outcome->sent);
        self::assertNull($outcome->stopped);
        self::assertSame(['pending', 'pending', 'pending'], array_map($this->gymEmail(...), $people));
        self::assertSame([], $this->mailer->sent);
    }

    public function testTwoSendsAtTheSameMomentNeverSendTheSameEmailTwice(): void
    {
        $people = [];
        foreach (['07:00', '09:30', '12:30'] as $index => $time) {
            $people[] = $this->cancelled($this->user('cliente' . $index . '@example.test'), '2026-12-09', $time);
        }
        // Due telefoni: mentre il primo invio manda la prima email, il secondo invio del modulo arriva e fa il suo giro.
        $mailer = new SlowMailer($this->mailer, $this->clock, 0);
        $other = $this->cancellations($mailer);
        $second = null;
        $mailer->during = function () use ($other, &$second): void {
            $second = $other->resend($this->staff);
        };

        $first = $this->cancellations($mailer)->resend($this->staff);

        // Ogni email la manda uno solo: la prima il primo invio (l'aveva presa), le altre il secondo.
        self::assertSame(1, $first->sent);
        self::assertInstanceOf(ResendOutcome::class, $second);
        self::assertSame(2, $second->sent);
        self::assertCount(3, $this->mailer->sent);
        foreach (['cliente0@example.test', 'cliente1@example.test', 'cliente2@example.test'] as $email) {
            self::assertCount(1, $this->mailer->sentTo($email), $email);
        }
        self::assertSame(['sent', 'sent', 'sent'], array_map($this->gymEmail(...), $people));
    }

    public function testSendingCountsTowardsTheGenerousLimitOfTheScheduleChanges(): void
    {
        $booking = $this->cancelled($this->user('anna@example.test'), '2026-12-09', '07:00');
        $limiter = $this->limiter();
        for ($attempt = 1; $attempt <= 150; ++$attempt) {
            self::assertTrue(StaffThrottle::hitSchedule($limiter, $this->staff)->allowed);
        }

        $throttled = $this->cancellations()->resend($this->staff);

        self::assertSame(ResendStatus::Throttled, $throttled->status);
        self::assertSame(900, $throttled->retryAfter);
        self::assertSame([], $this->mailer->sent);
        self::assertSame('pending', $this->gymEmail($booking), 'Oltre il limite non si prende nessuna email.');
    }

    /** Il servizio di una richiesta, come lo collega AdminModule: con il suo GymEmailSender. */
    private function cancellations(?Mailer $mailer = null, ?UserRepository $users = null): GymCancellations
    {
        $calendar = $this->calendar();
        $sender = new GymEmailSender($this->bookings, $users ?? new PdoUserRepository($this->database), $this->emails($calendar), $this->notifier($calendar, $mailer ?? $this->mailer), $this->clock);

        return new GymCancellations($calendar, $this->bookings, $sender, $this->limiter(), $this->clock);
    }

    /** Il calendario come lo vede il sito adesso: l'orario del database, letto da capo. */
    private function calendar(): BookingCalendar
    {
        return BookingCalendar::from(new PdoSchedule($this->database, $this->site->booking), $this->site);
    }

    private function notifier(BookingCalendar $calendar, Mailer $mailer): BookingNotifier
    {
        return new BookingNotifier($this->emails($calendar), $mailer, $this->reporter);
    }

    private function emails(BookingCalendar $calendar): BookingEmails
    {
        $view = new View(SiteFixture::templateDirectory(), new AssetUrls(SiteFixture::PUBLIC_DIR, SiteFixture::mtimes()), new Icons());

        return new BookingEmails(new MailTemplates($view), Origin::fromUrl('https://fourstreet.studio'), $this->site, new ItalianDateFormatter(), $calendar);
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter(new PdoRateLimitStore($this->database), new Fingerprinter(str_repeat('k', 32)), $this->clock);
    }

    /**
     * Una prenotazione del cliente disdetta dalla palestra, con l'email in quello stato (la disdetta la scrive in attesa).
     */
    private function cancelled(User $customer, string $date, string $time, GymEmail $email = GymEmail::Pending): Booking
    {
        $booking = $this->place($customer, $date, $time);
        self::assertTrue($this->bookings->cancel($booking->id, $this->clock->now(), CancelledBy::Admin));
        if ($email !== GymEmail::Pending) {
            self::assertTrue($this->bookings->claimGymEmail($booking->id));
            if ($email !== GymEmail::Sending) {
                $this->bookings->settleGymEmail($booking->id, $email);
            }
        }

        return $booking;
    }

    private function place(User $customer, string $date, string $time): Booking
    {
        $slot = $this->calendar()->slot(self::day($date), self::time($time)) ?? self::fail(sprintf('Nessuna fascia il %s alle %s.', $date, $time));

        return $this->bookings->place($customer->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer)->booking();
    }

    /** Com'è l'email della palestra di quella prenotazione nel database: "pending", "sent"…, oppure null. */
    private function gymEmail(Booking $booking): ?string
    {
        return $this->database->fetchOne('SELECT gym_email FROM bookings WHERE id = :id', ['id' => $booking->id])?->nullableString('gym_email');
    }

    private function user(string $email, string $name = 'Cliente Prova'): User
    {
        $users = new PdoUserRepository($this->database);
        $user = $users->create(new NewUser(self::person($name), EmailAddress::tryFrom($email) ?? self::fail(), 'hash', $this->clock->now(), str_repeat('0', 64)));
        $users->markEmailVerified($user, $this->clock->now());

        return $users->findById($user->id) ?? self::fail();
    }

    private static function day(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail($value);
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? self::fail($value);
    }

    private static function person(string $value): FullName
    {
        return FullName::tryFrom($value) ?? self::fail($value);
    }
}
