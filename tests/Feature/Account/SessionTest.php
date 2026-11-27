<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Account\Housekeeping;
use FourStreet\Account\Session\RequireLogin;
use FourStreet\Account\Session\SessionManager;
use FourStreet\Account\Session\StartSession;
use FourStreet\Application;
use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(SessionManager::class)]
#[CoversClass(StartSession::class)]
#[CoversClass(RequireLogin::class)]
#[CoversClass(Housekeeping::class)]
final class SessionTest extends TestCase
{
    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->browser = $this->fixture->browser();
        $this->fixture->registerAndVerify($this->browser);
    }

    public function testTheAccountPageAsksGuestsToLogInAndBringsThemBack(): void
    {
        $guest = $this->fixture->browser('198.51.100.1');

        $response = $guest->get('/account/');

        self::assertSame(303, $response->status);
        self::assertSame('/account/accedi/', $response->header('Location'));

        $protected = $guest->get('/account/?avviso=uscito');
        self::assertSame('/account/accedi/?ritorno=/account/%3Favviso%3Duscito', $protected->header('Location'));
    }

    public function testTheLoginPageOfAGuestNeedsNoDatabase(): void
    {
        // Un database che non si può aprire: la cartella del file starebbe dentro un file.
        $unreachable = new Database(DatabaseConfig::sqlite(__FILE__ . '/non-esiste/4street.sqlite'));
        $fixture = new AccountFixture(database: $unreachable);

        self::assertSame(200, $fixture->browser()->get('/account/accedi/')->status);
        self::assertSame(200, $fixture->browser()->get('/account/registrati/')->status);
        self::assertFalse($unreachable->isConnected());
    }

    public function testTheSessionLastsThirtyDaysWithoutVisits(): void
    {
        $this->fixture->clock->travel('+30 days -1 second');
        self::assertSame(200, $this->browser->get('/account/')->status);

        // Ogni visita la allunga: altri trenta giorni da qui.
        $this->fixture->clock->travel('+30 days -1 second');
        self::assertSame(200, $this->browser->get('/account/')->status);

        $this->fixture->clock->travel('+30 days');
        $expired = $this->browser->get('/account/');
        self::assertSame(303, $expired->status);
        self::assertNull($this->browser->cookie('__Host-session'), 'Il cookie di una sessione scaduta si cancella.');
        self::assertSame(0, $this->fixture->count('sessions'));
    }

    public function testAVisitRenewsTheCookieAtMostEveryFifteenMinutes(): void
    {
        $this->fixture->clock->travel('+10 minutes');
        self::assertSame([], self::sessionCookies($this->browser->get('/account/')));

        $this->fixture->clock->travel('+6 minutes');
        $renewed = self::sessionCookies($this->browser->get('/account/'));
        self::assertCount(1, $renewed);
        self::assertStringContainsString('Max-Age=2592000', $renewed[0]);
    }

    public function testAForgedOrStaleCookieIsDiscarded(): void
    {
        $intruder = $this->fixture->browser('198.51.100.2');
        $intruder->setCookie('__Host-session', str_repeat('A', 43));

        $response = $intruder->get('/account/');

        self::assertSame(303, $response->status);
        self::assertSame(['__Host-session=; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Lax'], self::sessionCookies($response));
    }

    public function testTheTokenIsNeverStoredInTheDatabase(): void
    {
        $token = $this->browser->cookie('__Host-session');
        self::assertNotNull($token);

        $row = $this->fixture->database->fetchOne('SELECT token_hash FROM sessions');
        self::assertNotNull($row);
        self::assertSame(hash('sha256', $token), $row->string('token_hash'));
    }

    public function testANewSessionReplacesTheOneThisBrowserHad(): void
    {
        $this->fixture->register($this->fixture->browser('198.51.100.4'), 'anna@example.com', 'la password di anna', 'Anna Bianchi');
        $link = InMemoryMailer::link($this->fixture->mailer->lastTo('anna@example.com'), '/account/verifica/');
        $before = $this->browser->cookie('__Host-session');

        // Sullo stesso browser, già dentro come Mario, si apre il link di conferma di Anna: da solo non cambia nulla.
        self::assertSame(200, $this->browser->get($link)->status);
        self::assertSame($before, $this->browser->cookie('__Host-session'));
        self::assertStringContainsString('Ciao, Mario Rossi.', $this->browser->get('/account/')->body);

        // Con la password di Anna si entra come Anna, e la sessione di Mario su questo browser si chiude.
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $token = $query['token'] ?? null;
        self::assertIsString($token);
        self::assertSame(303, $this->browser->post('/account/verifica/', ['token' => $token, 'password' => 'la password di anna'])->status);

        self::assertNotSame($before, $this->browser->cookie('__Host-session'));
        self::assertSame(1, $this->fixture->count('sessions'), 'La sessione di Mario su questo browser è chiusa.');
        self::assertStringContainsString('Ciao, Anna Bianchi.', $this->browser->get('/account/')->body);
    }

    public function testExpiredRowsAreCleanedUpAtTheNextLogin(): void
    {
        $other = $this->fixture->browser('198.51.100.3');
        $this->fixture->logIn($other);
        self::assertSame(2, $this->fixture->count('sessions'));

        $this->fixture->clock->travel('+31 days');
        $this->fixture->logIn($other);

        self::assertSame(1, $this->fixture->count('sessions'));
        self::assertSame(0, $this->fixture->count('account_tokens'));
    }

    public function testOnlyAPostWithTheCsrfTokenLogsOut(): void
    {
        // Aprire l'indirizzo chiede conferma, non fa uscire.
        $confirm = $this->browser->get('/account/esci/');
        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('Vuoi uscire?</h1>', $confirm->body);
        self::assertStringContainsString('<form method="post" action="/account/esci/" class="fs-actions" data-submit-once>', $confirm->body);
        self::assertSame(200, $this->browser->get('/account/')->status, 'La sessione è ancora aperta.');

        // Un modulo senza token è respinto, e ricaricare porta di nuovo alla conferma.
        $withoutToken = $this->browser->post('/account/esci/', ['csrf' => '']);
        self::assertSame(403, $withoutToken->status);
        self::assertStringContainsString('<a href="/account/esci/" class="fs-btn fs-btn--primary fs-btn--block">Ricarica e riprova</a>', $withoutToken->body);
        self::assertSame(200, $this->browser->get('/account/')->status, 'La sessione è ancora aperta.');

        self::assertSame('/account/accedi/', $this->fixture->browser('198.51.100.9')->get('/account/esci/')->header('Location'), 'Un ospite va alla pagina di accesso.');
    }

    public function testDevelopmentCookiesWorkOnPlainHttp(): void
    {
        $fixture = new AccountFixture('http://localhost:8000');
        $browser = $fixture->browser();
        $form = $browser->get('/account/registrati/');

        self::assertContains('csrf=' . $browser->csrfToken() . '; Path=/; HttpOnly; SameSite=Lax', TestBrowser::setCookieHeaders($form));

        $fixture->registerAndVerify($browser);
        self::assertNotNull($browser->cookie('session'));
        self::assertNull($browser->cookie('__Host-session'));
        self::assertSame(200, $browser->get('/account/')->status);
    }

    /**
     * HTTPS lo decide l'origine pubblica, come per i cookie: in https le pagine chiedono al browser di restarci
     * (HSTS), su http://localhost no; senza config/local.php l'origine è site_url.
     */
    public function testStrictTransportSecurityFollowsThePublicOriginLikeTheCookies(): void
    {
        self::assertSame('max-age=31536000', $this->browser->get('/account/')->header('Strict-Transport-Security'));
        self::assertFalse((new AccountFixture('http://localhost:8000'))->browser()->get('/account/accedi/')->hasHeader('Strict-Transport-Security'));
        self::assertSame('max-age=31536000', SiteFixture::application(FrozenClock::at(AccountFixture::NOW))->handle(Request::create('GET', '/links/'))->header('Strict-Transport-Security'));
    }

    /**
     * @return list<string>
     */
    private static function sessionCookies(Response $response): array
    {
        return array_values(array_filter(TestBrowser::setCookieHeaders($response), static fn(string $line): bool => str_starts_with($line, '__Host-session=')));
    }
}
