<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Account\AccountGuards;
use FourStreet\Account\Http\LoginController;
use FourStreet\Account\Session\RequireAdmin;
use FourStreet\Account\SignInDestination;
use FourStreet\Admin\AdminModule;
use FourStreet\Admin\Http\AdminLinkSection;
use FourStreet\Application;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Chi entra nel pannello del titolare: l'ospite passa dalla pagina di accesso e torna; il cliente trova la stessa
 * pagina di un indirizzo che non esiste; il titolare entra, e dopo l'accesso ci arriva da solo. Nessuna pagina
 * pubblica né di un cliente porta al pannello. Senza config/local.php il pannello risponde come l'area clienti.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(AccountGuards::class)]
#[CoversClass(RequireAdmin::class)]
#[CoversClass(LoginController::class)]
#[CoversClass(SignInDestination::class)]
#[CoversClass(AdminLinkSection::class)]
final class AdminAccessTest extends TestCase
{
    private const string DAY = '/gestione/?giorno=2026-12-07';

    private AccountFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->fixture->admin();
    }

    public function testAGuestGoesThroughTheLoginPageAndComesBackToTheDay(): void
    {
        $browser = $this->fixture->browser();

        $toLogin = $browser->get(self::DAY);
        self::assertSame(303, $toLogin->status);
        self::assertSame('/account/accedi/?ritorno=/gestione/%3Fgiorno%3D2026-12-07', $toLogin->header('Location'));
        self::assertSame('no-store', $toLogin->header('Cache-Control'));

        $form = $browser->follow($toLogin);
        self::assertStringContainsString('<input type="hidden" name="ritorno" value="/gestione/?giorno=2026-12-07">', $form->body);
        $loggedIn = $browser->post('/account/accedi/', ['email' => 'titolare@example.test', 'password' => AccountFixture::PASSWORD, 'ritorno' => self::DAY]);
        self::assertSame(self::DAY, $loggedIn->header('Location'));
        self::assertStringContainsString('Lunedì 7 dicembre.</h1>', $browser->follow($loggedIn)->body);
    }

    public function testTheAdminLandsOnThePanelAfterLoggingInAndCustomersOnTheirAccount(): void
    {
        $admin = $this->fixture->browser();
        self::assertSame('/gestione/', $this->fixture->logIn($admin, 'titolare@example.test')->header('Location'));
        // Già dentro, la pagina di accesso porta lì; una destinazione chiesta vale anche per il titolare.
        self::assertSame('/gestione/', $admin->get('/account/accedi/')->header('Location'));
        self::assertSame('/prenota/', $admin->get('/account/accedi/?ritorno=/prenota/')->header('Location'));

        $this->fixture->customer('cliente@example.test');
        self::assertSame('/account/', $this->fixture->logIn($this->fixture->browser(), 'cliente@example.test')->header('Location'));
    }

    public function testACustomerFindsTheSamePageAsAnAddressThatDoesNotExist(): void
    {
        $customer = $this->fixture->customer('cliente@example.test');
        $booking = $this->fixture->book($customer, '2026-12-07', '18:30');
        $browser = $this->fixture->browser();
        $this->fixture->logIn($browser, 'cliente@example.test');

        $missing = $browser->get('/pagina-che-non-esiste');
        self::assertSame(404, $missing->status);
        foreach ([
            $browser->get('/gestione/'),
            $browser->get(self::DAY),
            $browser->get('/gestione/prenotazioni/nuova/?giorno=2026-12-07&fascia=07%3A00'),
            $browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '07:00', 'nome' => 'Ospite Prova']),
            $browser->get('/gestione/prenotazioni/' . $booking->id . '/disdici/'),
            $browser->post('/gestione/prenotazioni/' . $booking->id . '/disdici/'),
            $browser->get('/gestione/calendario/'),
            $browser->get('/gestione/calendario/?mese=2026-12'),
            $browser->get('/gestione/impostazioni/'),
            $browser->post('/gestione/impostazioni/', ['prenotazioni' => 'spente', 'giorni-prenotabili' => '14', 'chiusura-prenotazioni' => '0', 'chiusura-disdette' => '0', 'prenotazioni-future' => '6']),
            $browser->get('/gestione/orari/'),
            $browser->post('/gestione/orari/'),
            $browser->get('/gestione/orari/settimana/?da=2026-12-07'),
            $browser->post('/gestione/orari/settimana/?da=2026-12-07'),
            $browser->get('/gestione/chiusure/'),
            $browser->post('/gestione/chiusure/'),
        ] as $response) {
            self::assertSame(404, $response->status);
            self::assertSame($missing->body, $response->body, 'La stessa pagina di un indirizzo che non esiste.');
        }

        self::assertSame(1, $this->fixture->count('bookings'), 'Nessuna prenotazione nuova.');
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'), 'E nessuna disdetta.');
        self::assertSame(0, $this->fixture->count('booking_settings'), 'Né regole cambiate.');
        self::assertSame(200, $browser->get('/prenota/')->status, 'Le prenotazioni restano accese.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function otherPanelPages(): iterable
    {
        yield 'il calendario' => ['/gestione/calendario/', '/account/accedi/?ritorno=/gestione/calendario/'];
        yield 'un mese' => ['/gestione/calendario/?mese=2026-12', '/account/accedi/?ritorno=/gestione/calendario/%3Fmese%3D2026-12'];
        yield 'le impostazioni' => ['/gestione/impostazioni/', '/account/accedi/?ritorno=/gestione/impostazioni/'];
        yield 'la settimana tipo' => ['/gestione/orari/', '/account/accedi/?ritorno=/gestione/orari/'];
        yield 'una settimana' => ['/gestione/orari/settimana/?da=2026-12-07', '/account/accedi/?ritorno=/gestione/orari/settimana/%3Fda%3D2026-12-07'];
        yield 'le chiusure' => ['/gestione/chiusure/', '/account/accedi/?ritorno=/gestione/chiusure/'];
    }

    #[DataProvider('otherPanelPages')]
    public function testAGuestGoesThroughTheLoginPageFromEveryPageOfThePanel(string $uri, string $login): void
    {
        $toLogin = $this->fixture->browser()->get($uri);

        self::assertSame(303, $toLogin->status);
        self::assertSame($login, $toLogin->header('Location'));
        self::assertSame('no-store', $toLogin->header('Cache-Control'));
    }

    public function testAfterLoggingInTheAdminComesBackToTheCalendarAndTheSettings(): void
    {
        foreach ([
            '/gestione/calendario/?mese=2026-12' => 'Dicembre 2026.</h1>',
            '/gestione/impostazioni/' => 'Impostazioni.</h1>',
        ] as $uri => $heading) {
            $browser = $this->fixture->browser();
            $browser->get($uri);
            $loggedIn = $browser->post('/account/accedi/', ['email' => 'titolare@example.test', 'password' => AccountFixture::PASSWORD, 'ritorno' => $uri]);
            self::assertSame($uri, $loggedIn->header('Location'));
            self::assertStringContainsString($heading, $browser->follow($loggedIn)->body);
        }
    }

    public function testThePanelPagesAreKeptOutOfCachesAndSearchEngines(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->logIn($browser, 'titolare@example.test');

        foreach (['/gestione/', self::DAY, '/gestione/prenotazioni/nuova/?giorno=2026-12-07&fascia=07%3A00', '/gestione/calendario/', '/gestione/calendario/?mese=2026-12', '/gestione/impostazioni/'] as $uri) {
            $page = $browser->get($uri);
            self::assertSame(200, $page->status, $uri);
            self::assertSame('no-store', $page->header('Cache-Control'), $uri);
            self::assertSame('noindex', $page->header('X-Robots-Tag'), $uri);
            self::assertStringContainsString('<meta name="robots" content="noindex">', $page->body, $uri);
        }
        self::assertSame(200, $browser->head(self::DAY)->status);
        self::assertSame(200, $browser->head('/gestione/calendario/')->status);
        self::assertSame(200, $browser->head('/gestione/impostazioni/')->status);
    }

    public function testOnlyTheAdminsAccountPageLinksThePanel(): void
    {
        $admin = $this->fixture->browser();
        $this->fixture->logIn($admin, 'titolare@example.test');
        $account = $admin->get('/account/');
        self::assertStringContainsString('<h2 id="gestione-titolo" class="fs-label text-fg-subtle">Gestione</h2>', $account->body);
        self::assertStringContainsString('<a href="/gestione/" class="fs-btn fs-btn--secondary fs-btn--block sm:w-auto sm:self-start">', $account->body);
        self::assertStringContainsString('<span>Gestione prenotazioni</span>', $account->body);

        $this->fixture->customer('cliente@example.test');
        $customer = $this->fixture->browser();
        $this->fixture->logIn($customer, 'cliente@example.test');
        foreach (['/account/', '/prenota/', '/', '/links/'] as $uri) {
            self::assertStringNotContainsString('/gestione/', $customer->get($uri)->body, $uri);
        }
        foreach (['/', '/links/', '/prenota/'] as $uri) {
            self::assertStringNotContainsString('/gestione/', $this->fixture->browser()->get($uri)->body, $uri);
        }
    }

    public function testRemovingTheRoleClosesThePanelAtTheNextPage(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->logIn($browser, 'titolare@example.test');
        self::assertSame(200, $browser->get('/gestione/')->status);

        // Il ruolo si legge a ogni pagina: anche con la sessione ancora aperta, il pannello non c'è più.
        $this->fixture->database->execute("UPDATE users SET role = 'customer' WHERE email = 'titolare@example.test'");

        self::assertSame(404, $browser->get('/gestione/')->status);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function panelPages(): iterable
    {
        foreach (AdminModule::ENDPOINTS as $path => $methods) {
            foreach ($methods as $method) {
                yield $method . ' ' . $path => [$method, str_replace('{id}', '1', $path)];
            }
        }
    }

    #[DataProvider('panelPages')]
    public function testWithoutTheBackendThePanelIsPolitelyUnavailable(string $method, string $uri): void
    {
        $response = SiteFixture::handle($method, $uri, AccountFixture::NOW);

        self::assertSame(503, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame('noindex', $response->header('X-Robots-Tag'));
        self::assertStringContainsString('Arriva presto.</h1>', $response->body);
    }
}
