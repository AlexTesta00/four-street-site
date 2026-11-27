<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Admin\AdminModule;
use FourStreet\Admin\Form\StaffBookingForm;
use FourStreet\Admin\Http\AdminNotice;
use FourStreet\Admin\Http\NewBookingController;
use FourStreet\Admin\Http\StaffBlockedPage;
use FourStreet\Application;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\Staff\StaffBooking;
use FourStreet\Booking\Staff\StaffThrottle;
use FourStreet\Http\Response;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La prenotazione inserita dal titolare, attraverso l'applicazione vera: per un cliente registrato (la vede nel suo
 * account e gli arriva la conferma con l'evento) o per una persona senza account (nessuna email). I posti valgono
 * anche per il titolare; le finestre di prenotazione e il massimo di prenotazioni future no; una al giorno per il
 * cliente sì. E le protezioni: token CSRF, limite dei tentativi, il secondo invio dello stesso modulo.
 * L'orologio è fermo a mercoledì 25 novembre 2026, 08:00.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(NewBookingController::class)]
#[CoversClass(StaffBookingForm::class)]
#[CoversClass(StaffBlockedPage::class)]
#[CoversClass(AdminNotice::class)]
#[CoversClass(StaffBooking::class)]
#[CoversClass(StaffThrottle::class)]
#[CoversClass(BookingEmails::class)]
#[CoversClass(PdoBookingRepository::class)]
final class AdminBookingTest extends TestCase
{
    private const string FORM = '/gestione/prenotazioni/nuova/?giorno=2026-12-07&fascia=09%3A30';

    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->fixture->admin();
        $this->browser = $this->fixture->browser();
        $this->fixture->logIn($this->browser, 'titolare@example.test');
    }

    public function testARegisteredCustomerIsBookedFindsItInTheAccountAndGetsTheConfirmation(): void
    {
        $this->fixture->customer('anna@example.test', 'Anna Bianchi');

        $form = $this->browser->get(self::FORM);
        self::assertSame(200, $form->status);
        self::assertStringContainsString('<title>4Street · Gestione · Aggiungi una persona</title>', $form->body);
        self::assertStringContainsString('Lunedì 7 dicembre, ore 09:30.</h1>', $form->body);
        self::assertStringContainsString('<span>1 di 5</span>', $form->body);
        self::assertStringContainsString('<form method="post" action="/gestione/prenotazioni/nuova/" class="fs-form" novalidate data-submit-once>', $form->body);
        self::assertStringContainsString('<input type="hidden" name="giorno" value="2026-12-07">', $form->body);
        self::assertStringContainsString('<input type="hidden" name="fascia" value="09:30">', $form->body);
        self::assertStringContainsString('<legend class="fs-choice__legend fs-label">Per chi è il posto</legend>', $form->body);
        self::assertStringContainsString('<input class="fs-input" id="email" name="email" type="email" value="" autocomplete="off"', $form->body);
        self::assertStringContainsString('<input class="fs-input" id="nome" name="nome" type="text" value="" autocomplete="off"', $form->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-07" class="fs-btn fs-btn--secondary fs-btn--block">Annulla</a>', $form->body);

        $booked = $this->book('2026-12-07', '09:30', ['email' => '  Anna@Example.TEST ']);
        self::assertSame(303, $booked->status);
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-inserita&prenotazione=1', $booked->header('Location'));
        self::assertStringContainsString('Anna Bianchi, lunedì 7 dicembre alle 09:30. Gli abbiamo mandato l&#039;email di conferma', $this->browser->follow($booked)->body);

        $row = $this->fixture->database->fetchOne('SELECT user_id, guest_name, seat, created_by FROM bookings WHERE id = 1');
        self::assertNotNull($row);
        self::assertSame($this->fixture->user('anna@example.test')->id, $row->nullableInt('user_id'));
        self::assertNull($row->nullableString('guest_name'));
        self::assertSame(1, $row->nullableInt('seat'));
        self::assertSame('admin', $row->string('created_by'));

        // La stessa conferma della prenotazione fatta da lei, con l'evento da aggiungere al calendario.
        $email = $this->fixture->mailer->lastTo('anna@example.test');
        self::assertSame('Prenotazione confermata: lunedì 7 dicembre, ore 09:30', $email->subject);
        self::assertStringContainsString("Ciao Anna Bianchi,\n\nil tuo posto è prenotato.", $email->text);
        self::assertSame('prenotazione-4street.ics', $email->attachments[0]->filename ?? null);
        self::assertSame([], $this->fixture->mailer->sentTo('titolare@example.test'));

        // E la prenotazione è nel suo account, da disdire come le altre.
        $anna = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($anna, 'anna@example.test');
        self::assertStringContainsString('<a href="/account/prenotazioni/1/disdici/"', $anna->get('/account/')->body);
    }

    public function testSomeoneWithoutAnAccountIsBookedWithTheNameAndNobodyIsEmailed(): void
    {
        $booked = $this->book('2026-12-07', '09:30', ['nome' => '  Marco   Neri ']);

        self::assertSame(303, $booked->status);
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-inserita&prenotazione=1', $booked->header('Location'));
        $day = $this->browser->follow($booked);
        self::assertStringContainsString('Marco Neri, lunedì 7 dicembre alle 09:30. Non ha l&#039;account: la prenotazione la vedi solo qui.', $day->body);
        self::assertStringContainsString('<p class="fs-roster__name"><span class="sr-only">Posto 1: </span>Marco Neri</p>', $day->body);

        $row = $this->fixture->database->fetchOne('SELECT user_id, guest_name, created_by FROM bookings WHERE id = 1');
        self::assertNotNull($row);
        self::assertNull($row->nullableInt('user_id'));
        self::assertSame('Marco Neri', $row->nullableString('guest_name'), 'Il nome con gli spazi in ordine.');
        self::assertSame('admin', $row->string('created_by'));
        self::assertSame([], $this->fixture->mailer->sent);
    }

    public function testTheEmailMustBeOfACustomerWhoConfirmedIt(): void
    {
        $this->fixture->register($this->fixture->browser('198.51.100.40'), 'sospeso@example.test');
        $emails = count($this->fixture->mailer->sent);

        foreach (['nessuno@example.test', 'sospeso@example.test'] as $email) {
            $response = $this->book('2026-12-07', '09:30', ['email' => $email]);

            self::assertSame(422, $response->status, $email);
            self::assertStringContainsString('<li><a href="#email">Nessun cliente con questa email, o non l&#039;ha ancora confermata. Controlla l&#039;indirizzo, oppure scrivi il nome qui sotto.</a></li>', $response->body);
            self::assertStringContainsString('value="' . $email . '"', $response->body, 'Il valore torna nel modulo.');
        }
        self::assertSame(0, $this->fixture->count('bookings'));
        self::assertCount($emails, $this->fixture->mailer->sent);
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function wrongForms(): iterable
    {
        yield 'né email né nome' => [['email' => ' ', 'nome' => ' '], 'email', 'Scrivi l&#039;email del cliente registrato, oppure il nome di chi non ha l&#039;account.'];
        yield 'email e nome insieme' => [['email' => 'anna@example.test', 'nome' => 'Anna'], 'nome', 'Scrivi l&#039;email oppure il nome, non tutti e due'];
        yield 'email scritta male' => [['email' => 'anna@', 'nome' => ''], 'email', 'Scrivi un indirizzo email valido, come nome@esempio.it.'];
        yield 'nome di una lettera' => [['email' => '', 'nome' => 'A'], 'nome', 'Scrivi il nome della persona, almeno due lettere.'];
        yield 'nome troppo lungo' => [['email' => '', 'nome' => str_repeat('a', 101)], 'nome', 'Il nome può avere al massimo 100 caratteri.'];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('wrongForms')]
    public function testExactlyOneOfEmailAndNameWrittenProperly(array $fields, string $field, string $message): void
    {
        $response = $this->book('2026-12-07', '09:30', $fields);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<title>Errore: 4Street · Gestione · Aggiungi una persona</title>', $response->body);
        self::assertStringContainsString('<li><a href="#' . $field . '">' . $message, $response->body);
        self::assertStringContainsString('aria-invalid="true" aria-describedby="' . $field, $response->body);
        self::assertSame(0, $this->fixture->count('bookings'));
    }

    public function testFiveSeatsAreFiveForTheAdminToo(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->fixture->book($this->fixture->customer($name . '@example.test'), '2026-12-07', '09:30');
        }

        $form = $this->browser->get(self::FORM);
        self::assertSame(409, $form->status);
        self::assertStringContainsString('Fascia al completo.</h1>', $form->body);
        self::assertStringContainsString('I 5 posti della fascia delle 09:30 di lunedì 7 dicembre sono tutti presi: per aggiungere qualcuno, prima disdici una prenotazione.', $form->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-07" class="fs-btn fs-btn--primary fs-btn--block">Torna al giorno</a>', $form->body);

        $sixth = $this->book('2026-12-07', '09:30', ['nome' => 'Sesto Ospite']);
        self::assertSame(409, $sixth->status);
        self::assertStringContainsString('Fascia al completo.</h1>', $sixth->body);
        self::assertSame('1,2,3,4,5', $this->fixture->seats('2026-12-07', '09:30'));
    }

    public function testTheAdminBooksUntilTheStartWhateverTheCustomersWindow(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['booking_closes_minutes_before' => 60])]);
        $fixture->admin();
        $browser = $fixture->browser();
        $fixture->logIn($browser, 'titolare@example.test');

        // Alle 17:45 il cliente non prenota più le 18:30; il titolare sì, fino all'inizio.
        $fixture->clock->moveTo('2026-12-07 17:45:00');
        self::assertSame(303, $browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '18:30', 'nome' => 'Ospite Prova'])->status);

        $fixture->clock->moveTo('2026-12-07 18:30:00');
        $started = $browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '18:30', 'nome' => 'Altro Ospite']);
        self::assertSame(409, $started->status);
        self::assertStringContainsString('La fascia è iniziata.</h1>', $started->body);
        self::assertStringContainsString('La fascia delle 18:30 di lunedì 7 dicembre è già iniziata: non si aggiunge più nessuno.', $started->body);
        self::assertSame(409, $browser->get('/gestione/prenotazioni/nuova/?giorno=2026-12-07&fascia=18%3A30')->status);
        self::assertSame(1, $fixture->count('bookings'));
    }

    public function testTheAdminBooksBeyondTheBookableDaysAndBeyondTheMaximumOfUpcomingBookings(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['max_upcoming' => 1])]);
        $fixture->admin();
        $browser = $fixture->browser();
        $fixture->logIn($browser, 'titolare@example.test');
        $anna = $fixture->customer('anna@example.test');
        $fixture->book($anna, '2026-12-08', '07:00');

        // Anna ha già la sua prenotazione futura (il massimo è una), e il 28 dicembre è oltre i quattordici giorni.
        foreach (['2026-12-07', '2026-12-28'] as $day) {
            $booked = $browser->post('/gestione/prenotazioni/nuova/', ['giorno' => $day, 'fascia' => '18:30', 'email' => 'anna@example.test']);
            self::assertSame(303, $booked->status, $day);
        }
        self::assertSame(3, $fixture->count('bookings'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missingSlots(): iterable
    {
        yield 'domenica, chiuso' => ['2026-12-13', '18:30'];
        yield 'prima dell\'inaugurazione' => ['2026-11-30', '18:30'];
        yield 'giorno di chiusura' => ['2026-12-08', '18:30'];
        yield 'orario che non c\'è' => ['2026-12-07', '08:00'];
    }

    #[DataProvider('missingSlots')]
    public function testThereAreNoSlotsOnClosedDaysNorBeforeTheOpening(string $day, string $time): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['closed_dates' => ['2026-12-08']])]);
        $fixture->admin();
        $browser = $fixture->browser();
        $fixture->logIn($browser, 'titolare@example.test');

        $form = $browser->get('/gestione/prenotazioni/nuova/?giorno=' . $day . '&fascia=' . rawurlencode($time));
        self::assertSame(404, $form->status);
        self::assertStringContainsString('Fascia non trovata.</h1>', $form->body);

        self::assertSame(404, $browser->post('/gestione/prenotazioni/nuova/', ['giorno' => $day, 'fascia' => $time, 'nome' => 'Ospite Prova'])->status);
        self::assertSame(0, $fixture->count('bookings'));
    }

    public function testARegisteredCustomerKeepsOneBookingPerDay(): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '07:00');

        $other = $this->book('2026-12-07', '09:30', ['email' => 'anna@example.test']);

        self::assertSame(409, $other->status);
        self::assertStringContainsString('<li><a href="#email">Questo cliente è già prenotato quel giorno, alle 07:00, e si prenota una fascia al giorno. Per spostarlo, disdici prima quella prenotazione.</a></li>', $other->body);
        self::assertSame(1, $this->fixture->count('bookings'));

        // Iniziata la fascia delle 07:00, non si disdice più: il modulo non propone di farlo.
        $this->fixture->clock->moveTo('2026-12-07 10:00:00');
        $later = $this->book('2026-12-07', '18:30', ['email' => 'anna@example.test']);
        self::assertSame(409, $later->status);
        self::assertStringContainsString('<li><a href="#email">Questo cliente quel giorno è già stato prenotato alle 07:00, e si prenota una fascia al giorno: quella fascia è già iniziata.</a></li>', $later->body);
        self::assertStringNotContainsString('disdici prima', $later->body);
    }

    public function testEverySubmissionCountsEvenWithAnUnknownEmailSoThePanelTellsNothingAboutAccounts(): void
    {
        $this->fixture->customer('anna@example.test', 'Anna Bianchi');
        for ($attempt = 1; $attempt <= 60; ++$attempt) {
            self::assertSame(422, $this->book('2026-12-07', '09:30', ['email' => 'nessuno' . $attempt . '@example.test'])->status, (string) $attempt);
        }

        // Oltre il limite un'email nota e una sconosciuta hanno la stessa risposta.
        $known = $this->book('2026-12-07', '09:30', ['email' => 'anna@example.test']);
        $unknown = $this->book('2026-12-07', '09:30', ['email' => 'nessuno@example.test']);
        self::assertSame(429, $known->status);
        self::assertSame(429, $unknown->status);
        self::assertSame($known->header('Retry-After'), $unknown->header('Retry-After'));
        self::assertStringNotContainsString('Nessun cliente con questa email', $unknown->body);
        self::assertSame(0, $this->fixture->count('bookings'));
        self::assertSame([], $this->fixture->mailer->sentTo('anna@example.test'));
    }

    public function testTheConfirmationDoesNotPromiseACancellationThatIsAlreadyClosed(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['cancel_closes_minutes_before' => 120])]);
        $fixture->admin();
        $fixture->customer('anna@example.test', 'Anna Bianchi');
        $browser = $fixture->browser();
        $fixture->logIn($browser, 'titolare@example.test');

        // Alle 17:45 il cliente non disdice più le 18:30 (due ore prima): la conferma gli dice di avvisare la palestra.
        $fixture->clock->moveTo('2026-12-07 17:45:00');
        self::assertSame(303, $browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '18:30', 'email' => 'anna@example.test', 'nome' => ''])->status);

        $email = $fixture->mailer->lastTo('anna@example.test');
        self::assertStringContainsString('Se non puoi venire, avvisa la palestra: per questa fascia la disdetta dal tuo account è già chiusa.', $email->text);
        self::assertStringContainsString('Se non puoi venire, avvisa la palestra: per questa fascia la disdetta dal tuo account è già chiusa.', (string) $email->html);
        self::assertStringNotContainsString('disdici dal tuo account', $email->text);
        $ics = ($email->attachments[0] ?? self::fail('Nessun allegato.'))->content;
        self::assertStringContainsString("\r\nDESCRIPTION:Posto 1 di 5.\r\n", $ics);
    }

    public function testTheSameFormSentTwiceBooksOnce(): void
    {
        $this->fixture->customer('anna@example.test', 'Anna Bianchi');

        self::assertSame(303, $this->book('2026-12-07', '09:30', ['email' => 'anna@example.test'])->status);
        $again = $this->book('2026-12-07', '09:30', ['email' => 'anna@example.test']);
        self::assertSame(303, $again->status);
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-gia-inserita&prenotazione=1', $again->header('Location'));
        self::assertStringContainsString('Anna Bianchi, lunedì 7 dicembre alle 09:30. Era già in questa fascia: non è cambiato nulla.', $this->browser->follow($again)->body);
        self::assertCount(1, $this->fixture->mailer->sentTo('anna@example.test'));

        // Per chi non ha l'account, lo stesso nome nella stessa fascia è la stessa persona (spazi a parte).
        self::assertSame(303, $this->book('2026-12-07', '09:30', ['nome' => 'Marco Neri'])->status);
        $guestAgain = $this->book('2026-12-07', '09:30', ['nome' => ' Marco  Neri']);
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-gia-inserita&prenotazione=2', $guestAgain->header('Location'));
        self::assertSame('1,2', $this->fixture->seats('2026-12-07', '09:30'));

        // In un'altra fascia, o con un altro nome, è un'altra prenotazione.
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-inserita&prenotazione=3', $this->book('2026-12-07', '12:30', ['nome' => 'Marco Neri'])->header('Location'));
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-inserita&prenotazione=4', $this->book('2026-12-07', '09:30', ['nome' => 'Marco Bianchi'])->header('Location'));
    }

    public function testABookingStandsEvenIfTheConfirmationDoesNotLeave(): void
    {
        $this->fixture->customer('anna@example.test', 'Anna Bianchi');
        $this->fixture->mailer->failing = true;

        $booked = $this->book('2026-12-07', '09:30', ['email' => 'anna@example.test']);

        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-inserita-senza-email&prenotazione=1', $booked->header('Location'));
        self::assertStringContainsString('Anna Bianchi, lunedì 7 dicembre alle 09:30. L&#039;email di conferma non è partita: avvisalo tu. La prenotazione vale.', $this->browser->follow($booked)->body);
        self::assertSame('1', $this->fixture->seats('2026-12-07', '09:30'));
        self::assertCount(1, $this->fixture->reporter->reported);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function tamperedForms(): iterable
    {
        yield 'senza fascia' => [['giorno' => '2026-12-07', 'nome' => 'Ospite Prova']];
        yield 'senza giorno' => [['fascia' => '09:30', 'nome' => 'Ospite Prova']];
        yield 'giorno scritto male' => [['giorno' => '07/12/2026', 'fascia' => '09:30', 'nome' => 'Ospite Prova']];
        yield 'orario scritto male' => [['giorno' => '2026-12-07', 'fascia' => '9:30', 'nome' => 'Ospite Prova']];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('tamperedForms')]
    public function testATamperedFormBooksNothing(array $fields): void
    {
        self::assertSame(404, $this->browser->post('/gestione/prenotazioni/nuova/', $fields)->status);
        self::assertSame(0, $this->fixture->count('bookings'));
    }

    public function testWithoutTheSlotTheFormSendsBackToTheDay(): void
    {
        self::assertSame('/gestione/?giorno=2026-12-07', $this->browser->get('/gestione/prenotazioni/nuova/?giorno=2026-12-07')->header('Location'));
        self::assertSame('/gestione/', $this->browser->get('/gestione/prenotazioni/nuova/')->header('Location'));
        self::assertSame(404, $this->browser->get('/gestione/prenotazioni/nuova/?giorno=domani&fascia=09%3A30')->status);
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeBooking(): void
    {
        $forged = $this->browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '09:30', 'nome' => 'Ospite Prova'], ['Sec-Fetch-Site' => 'cross-site']);
        self::assertSame(403, $forged->status);
        self::assertStringContainsString('Il modulo è scaduto.</h1>', $forged->body);

        $withoutToken = $this->browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '09:30', 'nome' => 'Ospite Prova', 'csrf' => str_repeat('A', 43)]);
        self::assertSame(403, $withoutToken->status);
        self::assertSame(0, $this->fixture->count('bookings'));
    }

    public function testTooManyChangesInAQuarterOfAnHourWait(): void
    {
        // Sessanta invii ogni quindici minuti per il titolare, prenotazioni e disdette insieme, riusciti o no.
        for ($attempt = 1; $attempt <= 60; ++$attempt) {
            self::assertNotSame(429, $this->book('2026-12-07', '07:00', ['nome' => 'Ospite ' . $attempt])->status, (string) $attempt);
        }

        $open = $this->book('2026-12-07', '09:30', ['nome' => 'Ospite Prova']);
        self::assertSame(429, $open->status);
        self::assertSame('900', $open->header('Retry-After'));
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $open->body);
        self::assertStringContainsString('Lunedì 7 dicembre, ore 09:30.</h1>', $open->body, 'Lo stesso modulo, con l\'avviso.');
        self::assertStringContainsString('value="Ospite Prova"', $open->body);
        // Anche verso una fascia piena: la sua pagina, con l'avviso e il 429.
        $full = $this->book('2026-12-07', '07:00', ['nome' => 'Ospite Prova']);
        self::assertSame(429, $full->status);
        self::assertStringContainsString('Fascia al completo.</h1>', $full->body);
        self::assertSame(5, $this->fixture->count('bookings'));

        $this->fixture->clock->travel('+15 minutes');
        self::assertSame(303, $this->book('2026-12-07', '09:30', ['nome' => 'Ospite Prova'])->status);
    }

    /**
     * @param array<string, string> $fields
     */
    private function book(string $date, string $time, array $fields): Response
    {
        return $this->browser->post('/gestione/prenotazioni/nuova/', ['giorno' => $date, 'fascia' => $time, 'email' => '', 'nome' => '', ...$fields]);
    }
}
