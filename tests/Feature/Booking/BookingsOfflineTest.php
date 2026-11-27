<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Booking;

use FourStreet\Application;
use FourStreet\Booking\BookingModule;
use FourStreet\Booking\Http\BookingsComingSoonController;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le prenotazioni spente: con booking.online a false, oppure sul server senza config/local.php.
 * Il sito resta com'era: fasce di esempio sulla landing, "Presto" nella pagina dei link,
 * e /prenota/ risponde con una pagina cortese (503), mai con un errore.
 */
#[CoversClass(Application::class)]
#[CoversClass(BookingModule::class)]
#[CoversClass(BookingsComingSoonController::class)]
final class BookingsOfflineTest extends TestCase
{
    private const string BEFORE = '2026-11-25 08:00:00';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function bookingPages(): iterable
    {
        yield 'le fasce' => ['GET', '/prenota/'];
        yield 'un giorno' => ['GET', '/prenota/?giorno=2026-12-07'];
        yield 'il riepilogo' => ['GET', '/prenota/conferma/?giorno=2026-12-07&fascia=18:30'];
        yield 'la conferma' => ['POST', '/prenota/conferma/'];
    }

    #[DataProvider('bookingPages')]
    public function testWithBookingsSwitchedOffThePagesSaySoon(string $method, string $uri): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['online' => false])]);
        $browser = $fixture->browser();

        $response = $method === 'GET' ? $browser->get($uri) : $browser->post($uri, ['giorno' => '2026-12-07', 'fascia' => '18:30']);

        self::assertSame(503, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString('Prenotazioni presto.</h1>', $response->body);
        self::assertStringContainsString('<a href="https://wa.me/393662546495" target="_blank" rel="noopener" class="fs-btn fs-btn--primary fs-btn--block">', $response->body);
        self::assertSame(0, $fixture->count('bookings'));
        self::assertSame([], $fixture->reporter->reported);
    }

    public function testWithBookingsSwitchedOffTheLandingShowsTheExamplesAndNeverReadsTheDatabase(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['online' => false])]);
        $home = $fixture->browser()->get('/');
        $illustrative = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertSame(200, $home->status);
        self::assertFalse($home->hasHeader('Cache-Control'));
        self::assertStringContainsString('<span class="fs-tag fs-tag--placeholder">[Fasce illustrative]</span>', $home->body);
        self::assertStringContainsString('<span id="prenota-presto" class="fs-tag">Presto</span>', $home->body);
        self::assertStringNotContainsString('<form method="get"', $home->body);
        // La sezione è quella di sempre; in più c'è solo il link all'area clienti (il server la offre).
        self::assertSame(self::section($illustrative), self::section($home->body));
        self::assertStringContainsString('<span class="fs-btn__label">Area clienti</span>', $home->body);

        $links = $fixture->browser()->get('/links/');
        self::assertStringContainsString('<div class="fs-link fs-link--soon" aria-disabled="true">', $links->body);
    }

    public function testWithBookingsSwitchedOffExistingBookingsCanStillBeCancelled(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['online' => false])]);
        $customer = $fixture->customer('anna@example.com');
        $booking = $fixture->book($customer, '2026-12-07', '18:30');
        $browser = $fixture->browser();
        $fixture->logIn($browser, 'anna@example.com');

        $account = $browser->get('/account/');
        self::assertStringContainsString('<p class="fs-agenda__meta">Posto 1 di 5</p>', $account->body);
        self::assertStringNotContainsString('Prenota un', $account->body, 'Senza prenotazioni dal sito non c\'è il link per prenotare.');

        self::assertSame(303, $browser->post('/account/prenotazioni/' . $booking->id . '/disdici/')->status);
        self::assertSame('', $fixture->seats('2026-12-07', '18:30'));

        // L'email della disdetta non invita a prenotare di nuovo: dal sito, per ora, non si può.
        $email = $fixture->mailer->lastTo('anna@example.com');
        self::assertSame('Prenotazione disdetta: lunedì 7 dicembre, ore 18:30', $email->subject);
        self::assertStringNotContainsString('/prenota/', $email->text);
        self::assertStringNotContainsString('/prenota/', (string) $email->html);
        self::assertStringContainsString("il posto è tornato libero.\n\nSe non sei stato tu", $email->text);
    }

    public function testTheAccountStaysUpEvenIfTheBookingsCannotBeRead(): void
    {
        // Dopo il deploy, prima di "migrate": la tabella delle prenotazioni non c'è ancora.
        $fixture = new AccountFixture();
        $browser = $fixture->browser();
        $fixture->registerAndVerify($browser);
        $fixture->database->execute('DROP TABLE bookings');

        $account = $browser->get('/account/');

        self::assertSame(200, $account->status);
        self::assertStringContainsString('Le prenotazioni non si possono mostrare adesso. Riprova tra qualche minuto.', $account->body);
        self::assertStringNotContainsString('Prenota una fascia', $account->body);
        self::assertStringContainsString('Ciao, Mario Rossi.</h1>', $account->body);
        self::assertCount(1, $fixture->reporter->reported);
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function unconfiguredServer(): iterable
    {
        yield 'le fasce' => ['GET', '/prenota/', 503, 'Prenotazioni presto.</h1>'];
        yield 'il riepilogo' => ['GET', '/prenota/conferma/?giorno=2026-12-07&fascia=18:30', 503, 'Prenotazioni presto.</h1>'];
        yield 'la conferma' => ['POST', '/prenota/conferma/', 503, 'Prenotazioni presto.</h1>'];
        yield 'la disdetta' => ['GET', '/account/prenotazioni/1/disdici/', 503, 'Arriva presto.</h1>'];
        yield 'la disdetta inviata' => ['POST', '/account/prenotazioni/1/disdici/', 503, 'Arriva presto.</h1>'];
    }

    #[DataProvider('unconfiguredServer')]
    public function testWithoutTheBackendTheBookingPagesAreCourteous(string $method, string $uri, int $status, string $text): void
    {
        $response = SiteFixture::handle($method, $uri, self::BEFORE);

        self::assertSame($status, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString($text, $response->body);
    }

    public function testWithoutTheBackendTheLandingAndTheLinksStayAsTheyWere(): void
    {
        $home = SiteFixture::handle('GET', '/', self::BEFORE);

        self::assertStringContainsString('[Fasce illustrative]', $home->body);
        self::assertStringNotContainsString('/account/', $home->body, 'Senza area clienti, nessun link verso di lei.');
        self::assertStringContainsString('<div class="fs-link fs-link--soon" aria-disabled="true">', SiteFixture::handle('GET', '/links/', self::BEFORE)->body);
    }

    public function testTheLinksPageLinksTheBookingsWhenTheyAreOnline(): void
    {
        $links = (new AccountFixture())->browser()->get('/links/');

        self::assertStringContainsString('<a href="/prenota/" class="fs-link">', $links->body);
        self::assertStringContainsString('<span class="fs-link__meta">Scegli la tua fascia</span>', $links->body);
        self::assertSame(1, substr_count($links->body, 'fs-link--soon'), 'Resta "Presto" solo lo shop.');
    }

    /** La sezione "Come funziona" di una landing. */
    private static function section(string $html): string
    {
        self::assertSame(1, preg_match('#<section id="fasce".*?</section>#s', $html, $match));

        return $match[0];
    }
}
