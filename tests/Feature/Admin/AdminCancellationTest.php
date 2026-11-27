<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Admin\AdminModule;
use FourStreet\Admin\Http\AdminNotice;
use FourStreet\Admin\Http\CancelBookingController;
use FourStreet\Application;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\Staff\StaffCancellation;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La disdetta fatta dal titolare, attraverso l'applicazione vera: prima la conferma, poi il POST. Al cliente
 * registrato arriva l'email che a disdire è stata la palestra; chi non ha l'account non riceve nulla.
 * Il titolare disdice fino all'inizio della fascia, qualunque sia la regola dei clienti.
 * L'orologio è fermo a mercoledì 25 novembre 2026, 08:00.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(CancelBookingController::class)]
#[CoversClass(AdminNotice::class)]
#[CoversClass(StaffCancellation::class)]
#[CoversClass(BookingNotifier::class)]
#[CoversClass(BookingEmails::class)]
final class AdminCancellationTest extends TestCase
{
    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->fixture->admin();
        $this->browser = $this->fixture->browser();
        $this->fixture->logIn($this->browser, 'titolare@example.test');
    }

    public function testTheAdminCancelsACustomersBookingAndTheCustomerIsToldByTheGym(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $uri = '/gestione/prenotazioni/' . $booking->id . '/disdici/';

        $confirm = $this->browser->get($uri);
        self::assertSame(200, $confirm->status);
        self::assertSame('no-store', $confirm->header('Cache-Control'));
        self::assertStringContainsString('Disdici la prenotazione?</h1>', $confirm->body);
        self::assertStringContainsString('<dd>Anna Bianchi<br><span class="text-body-sm text-fg-muted">anna@example.test</span></dd>', $confirm->body);
        self::assertStringContainsString('<dd>Lunedì 7 dicembre 2026</dd>', $confirm->body);
        self::assertStringContainsString('<dd>1 di 5</dd>', $confirm->body);
        self::assertStringContainsString('<form method="post" action="' . $uri . '" class="fs-actions" data-submit-once>', $confirm->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-07" class="fs-btn fs-btn--secondary fs-btn--block">Tienila</a>', $confirm->body);
        self::assertStringContainsString('Gli mandiamo un&#039;email per avvisarlo che la prenotazione l&#039;ha disdetta la palestra.', $confirm->body);
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'), 'La pagina di conferma non disdice.');

        $cancelled = $this->browser->post($uri);
        self::assertSame(303, $cancelled->status);
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-disdetta&prenotazione=' . $booking->id, $cancelled->header('Location'));
        self::assertSame('', $this->fixture->seats('2026-12-07', '18:30'), 'Il posto è tornato libero.');
        $row = $this->fixture->database->fetchOne('SELECT cancelled_by FROM bookings WHERE id = :id', ['id' => $booking->id]);
        self::assertSame('admin', $row?->nullableString('cancelled_by'));

        $day = $this->browser->follow($cancelled);
        self::assertStringContainsString('<p class="fs-alert__title">Prenotazione disdetta</p>', $day->body);
        self::assertStringContainsString('Anna Bianchi, lunedì 7 dicembre alle 18:30. Il posto è tornato libero. Gli abbiamo mandato un&#039;email per avvisarlo.', $day->body);

        // L'email dice che è stata la palestra: niente "se non sei stato tu", un'altra fascia e il WhatsApp.
        $email = $this->fixture->mailer->lastTo('anna@example.test');
        self::assertSame('Prenotazione disdetta dalla palestra: lunedì 7 dicembre, ore 18:30', $email->subject);
        self::assertSame('Anna Bianchi', $email->to->name);
        self::assertSame([], $email->attachments);
        self::assertStringContainsString("Ciao Anna Bianchi,\n\nla palestra ha disdetto la tua prenotazione di lunedì 7 dicembre 2026, dalle 18:30 alle 19:30. Ci dispiace per il cambio di programma.", $email->text);
        self::assertStringNotContainsString('Se non sei stato tu', $email->text);
        self::assertStringNotContainsString('password', (string) $email->html);
        self::assertSame('/prenota/', InMemoryMailer::link($email, '/prenota/'));
        self::assertStringContainsString("Per qualsiasi domanda scrivici su WhatsApp:\n\nhttps://wa.me/393662546495", $email->text);
        self::assertStringContainsString('<a href="https://wa.me/393662546495" style="color:#cdaa66;text-decoration:underline;">scrivici su WhatsApp</a>', (string) $email->html);
        self::assertStringContainsString('<title>Prenotazione disdetta</title>', (string) $email->html);

        // Il cliente non la trova più nel suo account.
        $anna = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($anna, 'anna@example.test');
        self::assertStringContainsString('Nessuna prenotazione.', $anna->get('/account/')->body);
    }

    public function testABookingWithoutAnAccountIsCancelledWithoutEmails(): void
    {
        $booking = $this->fixture->bookGuest($this->fixture->user('titolare@example.test'), 'Marco Neri', '2026-12-07', '18:30');
        $uri = '/gestione/prenotazioni/' . $booking->id . '/disdici/';

        $confirm = $this->browser->get($uri);
        self::assertStringContainsString('<dd>Marco Neri<br><span class="text-body-sm text-fg-muted">Senza account</span></dd>', $confirm->body);
        self::assertStringContainsString('Non ha l&#039;account: nessuna email. Avvisalo tu, per esempio su WhatsApp.', $confirm->body);

        $cancelled = $this->browser->post($uri);
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-disdetta&prenotazione=' . $booking->id, $cancelled->header('Location'));
        self::assertStringContainsString('Marco Neri, lunedì 7 dicembre alle 18:30. Il posto è tornato libero. Non ha l&#039;account: avvisalo tu.', $this->browser->follow($cancelled)->body);
        self::assertSame([], $this->fixture->mailer->sent);
    }

    public function testTheAdminCancelsUntilTheStartWhateverTheCustomersWindow(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['cancel_closes_minutes_before' => 120])]);
        $fixture->admin();
        $booking = $fixture->book($fixture->customer('anna@example.test'), '2026-12-07', '18:30');
        $anna = $fixture->browser('198.51.100.30');
        $fixture->logIn($anna, 'anna@example.test');
        $admin = $fixture->browser();
        $fixture->logIn($admin, 'titolare@example.test');

        // Alle 17:45 il cliente non disdice più (due ore prima); il titolare sì.
        $fixture->clock->moveTo('2026-12-07 17:45:00');
        self::assertSame(404, $anna->get('/account/prenotazioni/' . $booking->id . '/disdici/')->status);
        self::assertSame(303, $admin->post('/gestione/prenotazioni/' . $booking->id . '/disdici/')->status);
        self::assertSame('', $fixture->seats('2026-12-07', '18:30'));
    }

    public function testABookingThatHasStartedIsNoLongerCancelled(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test'), '2026-12-07', '18:30');
        $uri = '/gestione/prenotazioni/' . $booking->id . '/disdici/';
        $this->fixture->clock->moveTo('2026-12-07 18:30:00');

        $page = $this->browser->get($uri);
        self::assertSame(404, $page->status);
        self::assertStringContainsString('Niente da disdire.</h1>', $page->body);
        self::assertStringContainsString('<a href="/gestione/" class="fs-btn fs-btn--primary fs-btn--block">Torna alla gestione</a>', $page->body);
        self::assertSame(404, $this->browser->post($uri)->status);
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'));
        self::assertSame([], $this->fixture->mailer->sentTo('anna@example.test'));
    }

    public function testTheSameCancellationSentTwiceIsNotAnError(): void
    {
        $mine = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        self::assertSame(303, $this->browser->post('/gestione/prenotazioni/' . $mine->id . '/disdici/')->status);
        $emails = count($this->fixture->mailer->sent);

        $again = $this->browser->post('/gestione/prenotazioni/' . $mine->id . '/disdici/');
        self::assertSame(303, $again->status);
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-gia-disdetta&prenotazione=' . $mine->id, $again->header('Location'));
        self::assertStringContainsString('Anna Bianchi, lunedì 7 dicembre alle 18:30. Questa prenotazione era già disdetta: il posto è libero.', $this->browser->follow($again)->body);
        self::assertCount($emails, $this->fixture->mailer->sent);
        self::assertSame(404, $this->browser->get('/gestione/prenotazioni/' . $mine->id . '/disdici/')->status);

        // Disdetta nel frattempo dal cliente: per il titolare è "già disdetta", e resta del cliente.
        $luca = $this->fixture->book($this->fixture->customer('luca@example.test'), '2026-12-08', '18:30');
        $this->fixture->database->execute(
            "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:30:00', cancelled_by = 'customer' WHERE id = :id",
            ['id' => $luca->id],
        );
        self::assertSame('/gestione/?giorno=2026-12-08&avviso=prenotazione-gia-disdetta&prenotazione=' . $luca->id, $this->browser->post('/gestione/prenotazioni/' . $luca->id . '/disdici/')->header('Location'));
        self::assertSame('customer', $this->fixture->database->fetchOne('SELECT cancelled_by FROM bookings WHERE id = :id', ['id' => $luca->id])?->nullableString('cancelled_by'));
    }

    public function testACancellationStandsEvenIfTheEmailDoesNotLeave(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $this->fixture->mailer->failing = true;

        $cancelled = $this->browser->post('/gestione/prenotazioni/' . $booking->id . '/disdici/');

        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-disdetta-senza-email&prenotazione=' . $booking->id, $cancelled->header('Location'));
        self::assertStringContainsString('Il posto è tornato libero. L&#039;email di avviso non è partita: avvisalo tu.', $this->browser->follow($cancelled)->body);
        self::assertSame('', $this->fixture->seats('2026-12-07', '18:30'));
        self::assertCount(1, $this->fixture->reporter->reported);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingBookings(): iterable
    {
        yield 'inesistente' => ['999'];
        yield 'zero' => ['0'];
        yield 'con uno zero davanti' => ['01'];
        yield 'non un numero' => ['uno'];
    }

    #[DataProvider('missingBookings')]
    public function testABookingThatDoesNotExistIsNotFound(string $id): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test'), '2026-12-07', '18:30');

        self::assertSame(404, $this->browser->get('/gestione/prenotazioni/' . $id . '/disdici/')->status);
        self::assertSame(404, $this->browser->post('/gestione/prenotazioni/' . $id . '/disdici/')->status);
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'));
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeCancelling(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test'), '2026-12-07', '18:30');

        $forged = $this->browser->post('/gestione/prenotazioni/' . $booking->id . '/disdici/', [], ['Sec-Fetch-Site' => 'cross-site']);

        self::assertSame(403, $forged->status);
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'));
    }

    public function testCancellationsCountWithBookingsTowardsTheLimit(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test'), '2026-12-07', '18:30');
        for ($attempt = 1; $attempt <= 60; ++$attempt) {
            $this->browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '18:30', 'email' => '', 'nome' => 'Ospite ' . $attempt]);
        }

        $throttled = $this->browser->post('/gestione/prenotazioni/' . $booking->id . '/disdici/');

        self::assertSame(429, $throttled->status);
        self::assertSame('900', $throttled->header('Retry-After'));
        self::assertStringContainsString('Disdici la prenotazione?</h1>', $throttled->body, 'La stessa pagina di conferma, con l\'avviso.');
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $throttled->body);
        self::assertSame(1, $this->fixture->database->fetchOne('SELECT active FROM bookings WHERE id = :id', ['id' => $booking->id])?->nullableInt('active'), 'Ancora attiva.');
    }
}
