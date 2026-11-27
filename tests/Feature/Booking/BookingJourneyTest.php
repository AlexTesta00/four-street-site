<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Booking;

use FourStreet\Account\Http\AccountController;
use FourStreet\Application;
use FourStreet\Booking\BookingCancellation;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingModule;
use FourStreet\Booking\Http\BookingsSection;
use FourStreet\Booking\Http\CancelBookingController;
use FourStreet\Booking\Http\ConfirmBookingController;
use FourStreet\Booking\Http\DayController;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\SlotBooking;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\InMemoryMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Il percorso del cliente, attraverso l'applicazione vera: l'ospite sceglie la fascia, passa dalla
 * registrazione (o dall'accesso) e torna da solo al riepilogo, conferma, riceve l'email con l'evento,
 * trova la prenotazione nell'account, la disdice e riceve l'email della disdetta.
 * L'orologio è fermo a mercoledì 25 novembre 2026, 08:00: si prenota dal 7 dicembre (il 6, domenica,
 * è il giorno dell'inaugurazione ed è chiuso) al 9 dicembre (quattordici giorni dopo oggi).
 */
#[CoversClass(Application::class)]
#[CoversClass(BookingModule::class)]
#[CoversClass(DayController::class)]
#[CoversClass(ConfirmBookingController::class)]
#[CoversClass(CancelBookingController::class)]
#[CoversClass(BookingsSection::class)]
#[CoversClass(AccountController::class)]
#[CoversClass(SlotBooking::class)]
#[CoversClass(BookingCancellation::class)]
#[CoversClass(BookingEmails::class)]
#[CoversClass(PdoBookingRepository::class)]
final class BookingJourneyTest extends TestCase
{
    private const string CONFIRM = '/prenota/conferma/?giorno=2026-12-07&fascia=18%3A30';

    private AccountFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
    }

    public function testFromTheSlotToTheConfirmationEmailAndBackThroughTheCancellation(): void
    {
        $browser = $this->fixture->browser();

        // La pagina delle fasce: pubblica, privata per i motori di ricerca, il primo giorno con posti liberi.
        $day = $browser->get('/prenota/');
        self::assertSame(200, $day->status);
        self::assertSame('no-store', $day->header('Cache-Control'));
        self::assertSame('noindex', $day->header('X-Robots-Tag'));
        self::assertStringContainsString('<title>4Street · Prenota la fascia</title>', $day->body);
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-07" class="fs-day" aria-current="page">', $day->body);
        self::assertStringContainsString('<a href="' . htmlspecialchars(self::CONFIRM) . '" class="fs-slot__row fs-slot__row--link">', $day->body);
        self::assertSame(6, substr_count($day->body, 'fs-slot__row--link'));
        // All'ospite la pagina ricorda che per confermare serve l'account, e lo riporta qui dopo.
        self::assertStringContainsString('<a class="fs-text-link" href="/account/registrati/?ritorno=/prenota/">crealo</a>', $day->body);

        // Il riepilogo chiede l'accesso: si torna lì dopo, anche passando dalla registrazione e dall'email.
        $toLogin = $browser->get(self::CONFIRM);
        self::assertSame(303, $toLogin->status);
        self::assertSame('/account/accedi/?ritorno=/prenota/conferma/%3Fgiorno%3D2026-12-07%26fascia%3D18%253A30', $toLogin->header('Location'));
        $this->fixture->register($browser, returnTo: self::CONFIRM);
        $verified = $browser->get(InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/verifica/'));
        self::assertSame(self::CONFIRM, $verified->header('Location'));

        // Il riepilogo: giorno e ora nel titolo, il posto, l'indirizzo, un solo bottone.
        $summary = $browser->follow($verified);
        self::assertSame(200, $summary->status);
        self::assertSame('no-store', $summary->header('Cache-Control'));
        self::assertStringContainsString('Lunedì 7 dicembre, ore 18:30.</h1>', $summary->body);
        self::assertStringContainsString('<span class="sr-only">Dalle 18:30 alle 19:30</span><span aria-hidden="true">18:30 – 19:30</span>', $summary->body);
        self::assertStringContainsString('<span>1 di 5</span>', $summary->body);
        self::assertStringContainsString('<span class="fs-seat fs-seat--mine"></span>', $summary->body);
        self::assertStringContainsString('<form method="post" action="/prenota/conferma/" class="fs-actions" data-submit-once>', $summary->body);
        self::assertStringContainsString('<input type="hidden" name="giorno" value="2026-12-07">', $summary->body);
        self::assertStringContainsString('<input type="hidden" name="fascia" value="18:30">', $summary->body);
        self::assertSame(1, substr_count($summary->body, 'fs-btn--primary'));
        self::assertStringContainsString('Ti mandiamo la conferma a mario@example.com', $summary->body);
        self::assertSame(0, $this->fixture->count('bookings'), 'Il riepilogo non prenota.');

        // La conferma: 303 verso l'account (ricaricare non prenota due volte).
        $booked = $browser->post('/prenota/conferma/', ['giorno' => '2026-12-07', 'fascia' => '18:30']);
        self::assertSame(303, $booked->status);
        self::assertSame('/account/?avviso=prenotazione-confermata&prenotazione=1', $booked->header('Location'));
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'));

        // L'email di conferma, con l'evento da aggiungere al calendario.
        $email = $this->fixture->mailer->lastTo(AccountFixture::EMAIL);
        self::assertSame('Prenotazione confermata: lunedì 7 dicembre, ore 18:30', $email->subject);
        self::assertSame('Mario Rossi', $email->to->name);
        self::assertStringContainsString("Ciao Mario Rossi,\n\nil tuo posto è prenotato. Ti aspettiamo.\n\nGiorno: Lunedì 7 dicembre 2026\nOrario: 18:30 – 19:30\nPosto: 1 di 5\nDove: Via del Bando 22, San Marino", $email->text);
        self::assertStringContainsString('disdici dal tuo account fino all\'inizio della fascia', $email->text);
        self::assertSame('/account/', InMemoryMailer::link($email, '/account/'));
        self::assertCount(1, $email->attachments);
        self::assertSame('prenotazione-4street.ics', $email->attachments[0]->filename);
        self::assertSame('text/calendar; charset=utf-8; method=PUBLISH', $email->attachments[0]->contentType);
        $ics = $email->attachments[0]->content;
        self::assertStringContainsString("\r\nUID:prenotazione-1@fourstreet.studio\r\n", $ics);
        self::assertStringContainsString("\r\nDTSTART:20261207T173000Z\r\nDTEND:20261207T183000Z\r\n", $ics);
        self::assertStringContainsString("\r\nLOCATION:Via del Bando 22\\, San Marino\r\n", $ics);
        self::assertStringContainsString("\r\nURL:https://fourstreet.studio/account/\r\n", $ics);

        // L'account: l'avviso con la fascia e la prenotazione nell'elenco, con "Disdici".
        $account = $browser->follow($booked);
        self::assertSame(200, $account->status);
        self::assertStringContainsString('<p class="fs-alert__title">Prenotazione confermata</p>', $account->body);
        self::assertStringContainsString('Lunedì 7 dicembre alle 18:30. Ti abbiamo mandato un&#039;email con i dettagli', $account->body);
        self::assertStringContainsString('<ol class="fs-agenda" data-bookings>', $account->body);
        self::assertStringContainsString('<span class="sr-only">Lunedì 7 dicembre, dalle 18:30 alle 19:30</span><span aria-hidden="true">18:30 – 19:30</span>', $account->body);
        self::assertStringContainsString('<p class="fs-agenda__meta">Posto 1 di 5</p>', $account->body);
        self::assertStringContainsString('<a href="/account/prenotazioni/1/disdici/" class="fs-btn fs-btn--ghost fs-btn--sm fs-agenda__action" aria-label="Disdici la prenotazione di lunedì 7 dicembre alle 18:30">', $account->body);
        self::assertStringNotContainsString('Nessuna prenotazione.', $account->body);

        // Nella pagina delle fasce quel giorno è segnato, la fascia è "La tua" e le altre non si scelgono.
        $mine = $browser->get('/prenota/?giorno=2026-12-07');
        self::assertStringContainsString('Questo giorno sei prenotato alle 18:30.', $mine->body);
        self::assertStringContainsString('<div class="fs-slot__row fs-slot__row--mine">', $mine->body);
        self::assertStringContainsString('<span class="sr-only">, la tua prenotazione, posto 1</span>', $mine->body);
        self::assertStringNotContainsString('fs-slot__row--link', $mine->body);
        self::assertStringContainsString('<span class="fs-day__mark" aria-hidden="true"></span>', $mine->body);

        // La disdetta: prima la conferma (un link non disdice), poi il POST.
        $confirm = $browser->get('/account/prenotazioni/1/disdici/');
        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('Disdici la prenotazione?</h1>', $confirm->body);
        self::assertStringContainsString('<dd>Lunedì 7 dicembre 2026</dd>', $confirm->body);
        self::assertStringContainsString('<form method="post" action="/account/prenotazioni/1/disdici/" class="fs-actions" data-submit-once>', $confirm->body);
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'));

        $cancelled = $browser->post('/account/prenotazioni/1/disdici/');
        self::assertSame(303, $cancelled->status);
        self::assertSame('/account/?avviso=prenotazione-disdetta&prenotazione=1', $cancelled->header('Location'));
        self::assertSame('', $this->fixture->seats('2026-12-07', '18:30'), 'Il posto è tornato libero.');

        $email = $this->fixture->mailer->lastTo(AccountFixture::EMAIL);
        self::assertSame('Prenotazione disdetta: lunedì 7 dicembre, ore 18:30', $email->subject);
        self::assertSame([], $email->attachments);
        self::assertStringContainsString('hai disdetto la prenotazione di lunedì 7 dicembre 2026, dalle 18:30 alle 19:30: il posto è tornato libero.', $email->text);
        self::assertSame('/prenota/', InMemoryMailer::link($email, '/prenota/'));

        $after = $browser->follow($cancelled);
        self::assertStringContainsString('<p class="fs-alert__title">Prenotazione disdetta</p>', $after->body);
        self::assertStringContainsString('Lunedì 7 dicembre alle 18:30. Il posto è tornato libero.', $after->body);
        self::assertStringContainsString('Nessuna prenotazione.', $after->body);

        // La prenotazione resta come storico, con chi l'ha disdetta; la pagina della disdetta non c'è più.
        $row = $this->fixture->database->fetchOne('SELECT seat, active, cancelled_by FROM bookings WHERE id = 1');
        self::assertNotNull($row);
        self::assertNull($row->nullableInt('seat'));
        self::assertNull($row->nullableInt('active'));
        self::assertSame('customer', $row->nullableString('cancelled_by'));
        self::assertSame(404, $browser->get('/account/prenotazioni/1/disdici/')->status);

        // Lo stesso modulo inviato di nuovo (doppio tocco, due schede): all'account, senza errori né email.
        $emails = count($this->fixture->mailer->sent);
        $again = $browser->post('/account/prenotazioni/1/disdici/');
        self::assertSame(303, $again->status);
        self::assertSame('/account/?avviso=prenotazione-gia-disdetta&prenotazione=1', $again->header('Location'));
        self::assertStringContainsString('Lunedì 7 dicembre alle 18:30. Questa prenotazione è già disdetta', $browser->follow($again)->body);
        self::assertCount($emails, $this->fixture->mailer->sent);
    }

    public function testABookingCancelledByTheGymIsNotTheCustomersToCancelAgain(): void
    {
        $customer = $this->fixture->customer('anna@example.com');
        $booking = $this->fixture->book($customer, '2026-12-07', '18:30');
        $this->fixture->database->execute(
            "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:30:00', cancelled_by = 'admin' WHERE id = :id",
            ['id' => $booking->id],
        );
        $browser = $this->fixture->browser();
        $this->fixture->logIn($browser, 'anna@example.com');

        self::assertSame(404, $browser->post('/account/prenotazioni/' . $booking->id . '/disdici/')->status);
    }

    public function testAReturningCustomerLogsInAndComesBackToTheSummary(): void
    {
        $this->fixture->registerAndVerify($this->fixture->browser('198.51.100.20'));
        $browser = $this->fixture->browser();

        $toLogin = $browser->get(self::CONFIRM);
        $form = $browser->follow($toLogin);
        self::assertStringContainsString('<input type="hidden" name="ritorno" value="' . htmlspecialchars(self::CONFIRM) . '">', $form->body);

        $loggedIn = $browser->post('/account/accedi/', ['email' => AccountFixture::EMAIL, 'password' => AccountFixture::PASSWORD, 'ritorno' => self::CONFIRM]);
        self::assertSame(303, $loggedIn->status);
        self::assertSame(self::CONFIRM, $loggedIn->header('Location'));
        self::assertStringContainsString('Lunedì 7 dicembre, ore 18:30.</h1>', $browser->follow($loggedIn)->body);
    }

    public function testTheAccountListsOnlyBookingsThatAreNotOverAndOffersCancellationUntilTheStart(): void
    {
        $customer = $this->fixture->customer('anna@example.com', 'Anna Bianchi');
        $this->fixture->book($customer, '2026-12-07', '07:00');
        $this->fixture->book($customer, '2026-12-08', '18:30');
        $browser = $this->fixture->browser();
        self::assertSame(303, $this->fixture->logIn($browser, 'anna@example.com')->status);

        // Lunedì 7 alle 07:30 la prima è in corso: resta in elenco, senza "Disdici".
        $this->fixture->clock->moveTo('2026-12-07 07:30:00');
        $account = $browser->get('/account/');
        self::assertSame(2, substr_count($account->body, '<li class="fs-agenda__item'));
        self::assertStringContainsString('<li class="fs-agenda__item fs-agenda__item--now">', $account->body);
        self::assertStringContainsString('In corso</span>', $account->body);
        self::assertSame(1, substr_count($account->body, '>Disdici</span></a>'));
        self::assertSame(404, $browser->get('/account/prenotazioni/1/disdici/')->status, 'Iniziata, non si disdice più.');
        self::assertSame(404, $browser->post('/account/prenotazioni/1/disdici/')->status);

        // Finita, sparisce.
        $this->fixture->clock->moveTo('2026-12-07 08:00:00');
        $later = $browser->get('/account/');
        self::assertSame(1, substr_count($later->body, '<li class="fs-agenda__item'));
        self::assertStringNotContainsString('In corso', $later->body);
    }
}
