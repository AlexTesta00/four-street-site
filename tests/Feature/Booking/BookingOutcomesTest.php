<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Booking;

use FourStreet\Application;
use FourStreet\Booking\BookingModule;
use FourStreet\Booking\Http\BlockedBookingPage;
use FourStreet\Booking\Http\ConfirmBookingController;
use FourStreet\Booking\Http\DayController;
use FourStreet\Booking\Http\SlotSelection;
use FourStreet\Booking\SlotBooking;
use FourStreet\Http\Response;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le fasce che non si possono prenotare, con le pagine e i codici giusti: piena (anche nel frattempo),
 * già prenotato quel giorno, troppe prenotazioni future, chiusa, inesistente, parametri manomessi.
 * E le protezioni: token CSRF, limite dei tentativi, il secondo invio dello stesso modulo.
 */
#[CoversClass(Application::class)]
#[CoversClass(BookingModule::class)]
#[CoversClass(ConfirmBookingController::class)]
#[CoversClass(DayController::class)]
#[CoversClass(BlockedBookingPage::class)]
#[CoversClass(SlotSelection::class)]
#[CoversClass(SlotBooking::class)]
final class BookingOutcomesTest extends TestCase
{
    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->browser = $this->fixture->browser();
        $this->fixture->registerAndVerify($this->browser);
    }

    public function testAFullSlotCannotBeBookedAndTheSixthPersonIsTurnedAway(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->fixture->book($this->fixture->customer($name . '@example.com'), '2026-12-07', '18:30');
        }
        self::assertSame('1,2,3,4,5', $this->fixture->seats('2026-12-07', '18:30'));

        // Nella pagina delle fasce non è un link; il riepilogo e la conferma rispondono 409.
        $day = $this->browser->get('/prenota/?giorno=2026-12-07');
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>18:30<span class="sr-only">, completa</span>', $day->body);
        self::assertStringContainsString('<span class="fs-slot__status fs-tag" aria-hidden="true">Completo</span>', $day->body);
        self::assertSame(5, substr_count($day->body, 'fs-slot__row--link'));

        $summary = $this->browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=18:30');
        self::assertSame(409, $summary->status);
        self::assertStringContainsString('Fascia al completo.</h1>', $summary->body);
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-07" class="fs-btn fs-btn--primary fs-btn--block">Scegli un&#039;altra fascia</a>', $summary->body);

        $sixth = $this->book('2026-12-07', '18:30');
        self::assertSame(409, $sixth->status);
        self::assertStringContainsString('Fascia al completo.</h1>', $sixth->body);
        self::assertSame('1,2,3,4,5', $this->fixture->seats('2026-12-07', '18:30'));
        self::assertSame([], $this->fixture->mailer->sentTo('mario@example.com', subject: 'Prenotazione confermata'));
    }

    public function testTheSlotFillsUpWhileTheCustomerIsLookingAtTheSummary(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->fixture->book($this->fixture->customer($name . '@example.com'), '2026-12-07', '18:30');
        }
        $summary = $this->browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=18:30');
        self::assertSame(200, $summary->status);
        self::assertStringContainsString('<span>5 di 5</span>', $summary->body);

        // Un altro prende l'ultimo posto prima del tocco su "Conferma".
        $this->fixture->book($this->fixture->customer('ultimo@example.com'), '2026-12-07', '18:30');

        $late = $this->book('2026-12-07', '18:30');
        self::assertSame(409, $late->status);
        self::assertStringContainsString('Mentre sceglievi, gli ultimi posti della fascia delle 18:30 di lunedì 7 dicembre sono stati presi.', $late->body);
    }

    public function testAFreedSeatGoesToTheNextCustomer(): void
    {
        $bookings = [];
        foreach (['a', 'b', 'c'] as $name) {
            $bookings[] = $this->fixture->book($this->fixture->customer($name . '@example.com'), '2026-12-07', '18:30');
        }
        $this->fixture->database->execute(
            "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:00:00', cancelled_by = 'admin' WHERE id = :id",
            ['id' => $bookings[1]->id],
        );

        self::assertStringContainsString('<span>2 di 5</span>', $this->browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=18:30')->body);
        self::assertSame(303, $this->book('2026-12-07', '18:30')->status);
        self::assertSame('1,2,3', $this->fixture->seats('2026-12-07', '18:30'));
    }

    public function testOneBookingPerDayAndTheSameFormSentTwiceIsNotAnError(): void
    {
        self::assertSame(303, $this->book('2026-12-07', '18:30')->status);

        // Lo stesso modulo di nuovo (indietro e invia, doppio tocco): all'account, con la prenotazione che c'è.
        $again = $this->book('2026-12-07', '18:30');
        self::assertSame(303, $again->status);
        self::assertSame('/account/?avviso=prenotazione-gia-tua&prenotazione=1', $again->header('Location'));
        self::assertStringContainsString('Lunedì 7 dicembre alle 18:30. Questa fascia è già prenotata a tuo nome', $this->browser->follow($again)->body);
        self::assertCount(1, $this->fixture->mailer->sentTo('mario@example.com', subject: 'Prenotazione confermata'));

        // Un'altra fascia lo stesso giorno: 409, e la pagina dice quale c'è già.
        $other = $this->book('2026-12-07', '07:00');
        self::assertSame(409, $other->status);
        self::assertStringContainsString('Hai già una prenotazione.</h1>', $other->body);
        self::assertStringContainsString('Lunedì 7 dicembre sei prenotato alle 18:30.', $other->body);
        self::assertSame(409, $this->browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=07:00')->status);
        self::assertSame(1, $this->fixture->count('bookings'));

        // Un altro giorno sì.
        self::assertSame(303, $this->book('2026-12-08', '07:00')->status);
    }

    public function testABookingThatHasAlreadyStartedThatDayIsNotOfferedForCancellation(): void
    {
        // Prenotato alle 07:00 di lunedì 7; alle 10:00 quella fascia è passata e non si disdice più.
        self::assertSame(303, $this->book('2026-12-07', '07:00')->status);
        $this->fixture->clock->moveTo('2026-12-07 10:00:00');

        $other = $this->browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=18:30');
        self::assertSame(409, $other->status);
        self::assertStringContainsString('Lunedì 7 dicembre sei prenotato alle 07:00. Si prenota una fascia al giorno: scegli un altro giorno.', $other->body);
        self::assertStringNotContainsString('disdici', $other->body);
        self::assertStringContainsString('<a href="/prenota/" class="fs-btn fs-btn--primary fs-btn--block">Scegli un altro giorno</a>', $other->body);

        $day = $this->browser->get('/prenota/?giorno=2026-12-07');
        self::assertStringContainsString('Questo giorno sei prenotato alle 07:00, e non si disdice più.', $day->body);
        self::assertStringNotContainsString('per cambiarla, disdici', $day->body);
    }

    public function testAtMostSixUpcomingBookingsAndPastOnesDoNotCount(): void
    {
        // Con quattordici giorni prenotabili: da lunedì 7 a sabato 12, sei giorni, sei prenotazioni.
        $this->fixture->clock->moveTo('2026-12-06 20:00:00');
        foreach (['07', '08', '09', '10', '11', '12'] as $day) {
            self::assertSame(303, $this->book('2026-12-' . $day, '18:30')->status, $day);
        }

        $seventh = $this->book('2026-12-14', '18:30');
        self::assertSame(409, $seventh->status);
        self::assertStringContainsString('Hai già il massimo di prenotazioni.</h1>', $seventh->body);
        self::assertStringContainsString('Si possono avere al massimo 6 prenotazioni in arrivo, una al giorno.', $seventh->body);
        $day = $this->browser->get('/prenota/?giorno=2026-12-14');
        self::assertStringContainsString('Hai già 6 prenotazioni in arrivo: è il massimo.', $day->body);
        self::assertStringNotContainsString('fs-slot__row--link', $day->body);

        // Passata la prima, se ne prenota un'altra.
        $this->fixture->clock->moveTo('2026-12-07 19:30:00');
        self::assertSame(303, $this->book('2026-12-14', '18:30')->status);
    }

    public function testASlotThatHasStartedOrHasPassedIsClosed(): void
    {
        $this->fixture->clock->moveTo('2026-12-07 18:30:00');

        $started = $this->book('2026-12-07', '18:30');
        self::assertSame(409, $started->status);
        self::assertStringContainsString('Prenotazioni chiuse.</h1>', $started->body);
        self::assertStringContainsString('La fascia delle 18:30 di lunedì 7 dicembre non si prenota più: si prenota fino all&#039;inizio della fascia.', $started->body);

        // Un minuto prima si prenotava ancora; nella pagina delle fasce è "Chiusa".
        $day = $this->browser->get('/prenota/?giorno=2026-12-07');
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>18:30<span class="sr-only">, prenotazioni chiuse</span>', $day->body);
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>20:00<span class="sr-only">, 0 posti occupati su 5</span>', $day->body);
        self::assertSame(409, $this->book('2026-12-07', '07:00')->status);
        self::assertSame(0, $this->fixture->count('bookings'));
    }

    public function testBookingClosesTheConfiguredMinutesBeforeTheStart(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['booking_closes_minutes_before' => 30])]);
        $browser = $fixture->browser();
        $fixture->registerAndVerify($browser);

        $fixture->clock->moveTo('2026-12-07 17:59:59');
        self::assertSame(200, $browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=18:30')->status);
        $fixture->clock->moveTo('2026-12-07 18:00:00');
        $closed = $browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=18:30');
        self::assertSame(409, $closed->status);
        self::assertStringContainsString('si prenota fino a 30 minuti prima dell&#039;inizio.', $closed->body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingSlots(): iterable
    {
        yield 'orario che non c\'è' => ['/prenota/conferma/?giorno=2026-12-07&fascia=08:00'];
        yield 'domenica, chiuso' => ['/prenota/conferma/?giorno=2026-12-13&fascia=18:30'];
        yield 'prima dell\'inaugurazione' => ['/prenota/conferma/?giorno=2026-11-30&fascia=18:30'];
        yield 'oltre i giorni prenotabili' => ['/prenota/conferma/?giorno=2026-12-10&fascia=18:30'];
        yield 'giorno inesistente' => ['/prenota/conferma/?giorno=2026-02-30&fascia=18:30'];
        yield 'giorno scritto male' => ['/prenota/conferma/?giorno=7-12-2026&fascia=18:30'];
        yield 'orario scritto male' => ['/prenota/conferma/?giorno=2026-12-07&fascia=18.30'];
        yield 'orario senza zero' => ['/prenota/conferma/?giorno=2026-12-07&fascia=7:00'];
        yield 'testo qualunque' => ['/prenota/conferma/?giorno=%3Cscript%3E&fascia=18:30'];
        yield 'giorno della pagina delle fasce scritto male' => ['/prenota/?giorno=domani'];
    }

    #[DataProvider('missingSlots')]
    public function testASlotThatDoesNotExistIsNotFound(string $uri): void
    {
        $response = $this->browser->get($uri);

        self::assertSame(404, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString('Fascia non trovata.</h1>', $response->body);
        self::assertStringContainsString('<a href="/prenota/" class="fs-btn fs-btn--primary fs-btn--block">Scegli la fascia</a>', $response->body);
        self::assertStringNotContainsString('<script>', $response->body);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function tamperedForms(): iterable
    {
        yield 'senza fascia' => [['giorno' => '2026-12-07']];
        yield 'senza giorno' => [['fascia' => '18:30']];
        yield 'fascia inesistente' => [['giorno' => '2026-12-07', 'fascia' => '08:00']];
        yield 'giorno scritto male' => [['giorno' => '07/12/2026', 'fascia' => '18:30']];
        yield 'orario scritto male' => [['giorno' => '2026-12-07', 'fascia' => '18:30:00']];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('tamperedForms')]
    public function testATamperedFormBooksNothing(array $fields): void
    {
        $response = $this->browser->post('/prenota/conferma/', $fields);

        self::assertSame(404, $response->status);
        self::assertSame(0, $this->fixture->count('bookings'));
    }

    public function testWithoutTheSlotTheSummarySendsBackToTheChoice(): void
    {
        $withoutTime = $this->browser->get('/prenota/conferma/?giorno=2026-12-08');
        self::assertSame(303, $withoutTime->status);
        self::assertSame('/prenota/?giorno=2026-12-08', $withoutTime->header('Location'));

        $empty = $this->browser->get('/prenota/conferma/');
        self::assertSame(303, $empty->status);
        self::assertSame('/prenota/', $empty->header('Location'));
    }

    public function testADayOutsideTheBookableDaysShowsTheFirstFreeOneWithANote(): void
    {
        $page = $this->browser->get('/prenota/?giorno=2026-12-20');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('Il giorno che hai aperto non si prenota: ecco i prossimi.', $page->body);
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-07" class="fs-day" aria-current="page">', $page->body);
    }

    public function testTheDayPickerShowsClosedAndFullDays(): void
    {
        foreach (['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'] as $time) {
            foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
                $this->fixture->book($this->fixture->customer($name . str_replace(':', '', $time) . '@example.com'), '2026-12-08', $time);
            }
        }

        $page = $this->browser->get('/prenota/');

        // La domenica dell'inaugurazione non ha fasce: non è un link.
        self::assertStringContainsString('<span class="fs-day fs-day--quiet fs-day--off">', $page->body);
        self::assertStringContainsString('<span class="sr-only">Domenica 6 dicembre, nessuna fascia</span>', $page->body);
        // Martedì 8 è tutto pieno: si apre, ma si fa da parte.
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-08" class="fs-day fs-day--quiet">', $page->body);
        self::assertStringContainsString('<span class="sr-only">Martedì 8 dicembre, al completo</span>', $page->body);
        // Il mese compare nella prima casella e quando cambia.
        self::assertSame(1, substr_count($page->body, 'fs-day__month'));
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeBooking(): void
    {
        $forged = $this->browser->post('/prenota/conferma/', ['giorno' => '2026-12-07', 'fascia' => '18:30'], ['Sec-Fetch-Site' => 'cross-site']);
        self::assertSame(403, $forged->status);
        self::assertStringContainsString('Il modulo è scaduto.</h1>', $forged->body);

        $withoutToken = $this->browser->post('/prenota/conferma/', ['giorno' => '2026-12-07', 'fascia' => '18:30', 'csrf' => str_repeat('A', 43)]);
        self::assertSame(403, $withoutToken->status);
        self::assertSame(0, $this->fixture->count('bookings'));
    }

    public function testTooManyAttemptsWaitWithTheSamePageOfTheAccountArea(): void
    {
        // Dodici invii ogni quindici minuti per account, prenotazioni e disdette insieme.
        for ($attempt = 1; $attempt <= 12; ++$attempt) {
            self::assertNotSame(429, $this->book('2026-12-07', '18:30')->status, (string) $attempt);
        }

        $blocked = $this->book('2026-12-08', '18:30');
        self::assertSame(429, $blocked->status);
        self::assertSame('900', $blocked->header('Retry-After'));
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $blocked->body);
        self::assertStringContainsString('Martedì 8 dicembre, ore 18:30.</h1>', $blocked->body, 'La stessa pagina del riepilogo, con l\'avviso.');
        self::assertSame(1, $this->fixture->count('bookings'));

        $this->fixture->clock->travel('+15 minutes');
        self::assertSame(303, $this->book('2026-12-08', '18:30')->status);
    }

    public function testTheLimitPerAddressCountsEveryAccountBehindIt(): void
    {
        // Sessanta invii ogni quindici minuti dallo stesso indirizzo IP, qualunque sia l'account:
        // sei clienti, dieci invii ciascuno (sotto il loro limite di dodici).
        $browser = $this->fixture->browser('192.0.2.50');
        foreach (['uno', 'due', 'tre', 'quattro', 'cinque', 'sei'] as $name) {
            $browser = $this->fixture->browser('192.0.2.50');
            $this->fixture->customer($name . '@example.com');
            $this->fixture->logIn($browser, $name . '@example.com');
            for ($attempt = 1; $attempt <= 10; ++$attempt) {
                self::assertNotSame(429, $browser->post('/prenota/conferma/', ['giorno' => '2026-12-09', 'fascia' => '07:00'])->status, $name . ' ' . $attempt);
            }
        }

        // Il sessantunesimo, dal sesto cliente che ne ha mandati solo dieci, aspetta.
        self::assertSame(429, $browser->post('/prenota/conferma/', ['giorno' => '2026-12-09', 'fascia' => '09:30'])->status);
    }

    private function book(string $date, string $time): Response
    {
        return $this->browser->post('/prenota/conferma/', ['giorno' => $date, 'fascia' => $time]);
    }
}
