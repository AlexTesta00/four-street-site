<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Account\Http\LoginController;
use FourStreet\Account\Login;
use FourStreet\Account\ReturnPath;
use FourStreet\Application;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(LoginController::class)]
#[CoversClass(Login::class)]
#[CoversClass(ReturnPath::class)]
final class LoginTest extends TestCase
{
    private AccountFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->fixture->registerAndVerify($this->fixture->browser('192.0.2.200'));
    }

    public function testTheLoginForm(): void
    {
        $response = $this->fixture->browser()->get('/account/accedi/');

        self::assertSame(200, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString('<title>4Street · Accedi</title>', $response->body);
        self::assertStringContainsString('type="email" value="" autocomplete="username" inputmode="email"', $response->body);
        self::assertStringContainsString('type="password" autocomplete="current-password" required maxlength="128"', $response->body);
        self::assertStringContainsString('href="/account/password-dimenticata/">Password dimenticata?</a>', $response->body);
        self::assertStringContainsString('href="/account/registrati/">Registrati</a>', $response->body);
    }

    public function testALoginOpensANewSessionAndGoesToTheAccount(): void
    {
        $browser = $this->fixture->browser();

        $response = $this->fixture->logIn($browser);

        self::assertSame(303, $response->status);
        self::assertSame('/account/', $response->header('Location'));
        self::assertNotNull($browser->cookie('__Host-session'));
        self::assertSame(200, $browser->follow($response)->status);
    }

    public function testTheEmailIsNormalized(): void
    {
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser(), '  MARIO@Example.COM ')->status);
    }

    public function testEveryLoginGetsAFreshToken(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->logIn($browser);
        $first = $browser->cookie('__Host-session');
        $browser->post('/account/esci/');
        $this->fixture->logIn($browser);

        self::assertNotNull($first);
        self::assertNotSame($first, $browser->cookie('__Host-session'));
        self::assertSame(2, $this->fixture->count('sessions'), 'La sessione della registrazione e quella nuova.');
    }

    public function testAfterLoginTheVisitorReturnsToTheDestination(): void
    {
        $browser = $this->fixture->browser();
        $form = $browser->get('/account/accedi/?ritorno=/prenota/2026-12-08/');
        self::assertStringContainsString('<input type="hidden" name="ritorno" value="/prenota/2026-12-08/">', $form->body);
        self::assertStringContainsString('href="/account/registrati/?ritorno=/prenota/2026-12-08/"', $form->body);

        $response = $browser->post('/account/accedi/', ['email' => AccountFixture::EMAIL, 'password' => AccountFixture::PASSWORD, 'ritorno' => '/prenota/2026-12-08/']);

        self::assertSame('/prenota/2026-12-08/', $response->header('Location'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignDestinations(): iterable
    {
        yield 'altro sito' => ['https://evil.example/'];
        yield 'senza schema' => ['//evil.example/'];
        yield 'barra rovesciata' => ['/\evil.example/'];
        yield 'tabulazione' => ["/\t/evil.example/"];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'relativo' => ['prenota/'];
        yield 'punti' => ['/account/../account/esci/'];
        yield 'pagina di uscita' => ['/account/esci/'];
        yield 'pagina di accesso' => ['/account/accedi/?ritorno=/x/'];
        yield 'troppo lungo' => ['/' . str_repeat('a', 600)];
    }

    #[DataProvider('foreignDestinations')]
    public function testOnlyInternalDestinationsAreAccepted(string $destination): void
    {
        $browser = $this->fixture->browser();
        $browser->get('/account/accedi/');

        $response = $browser->post('/account/accedi/', ['email' => AccountFixture::EMAIL, 'password' => AccountFixture::PASSWORD, 'ritorno' => $destination]);

        self::assertSame(303, $response->status);
        self::assertSame('/account/', $response->header('Location'));
        self::assertStringNotContainsString('ritorno', $browser->get('/account/accedi/?ritorno=' . rawurlencode($destination))->header('Location') ?? '');
    }

    /**
     * Password sbagliata, email sconosciuta, account in sospeso con la password giusta o sbagliata: la stessa
     * pagina. Chiunque può registrare un indirizzo non suo e sceglierne la password: se l'account in sospeso
     * si distinguesse, saprebbe se quell'indirizzo aveva già un account confermato.
     */
    public function testEveryFailedLoginLooksTheSameEvenForAnUnconfirmedAccount(): void
    {
        $this->fixture->register($this->fixture->browser('198.51.100.7'), 'anna@example.com', 'la password di anna');
        $wrongPassword = $this->fixture->logIn($this->fixture->browser(), password: 'password sbagliata');
        $others = [
            'nessuno@example.com' => $this->fixture->logIn($this->fixture->browser('198.51.100.3'), 'nessuno@example.com', 'password sbagliata'),
            'anna@example.com' => $this->fixture->logIn($this->fixture->browser('198.51.100.4'), 'anna@example.com', 'la password di anna'),
        ];
        $unconfirmedWrongPassword = $this->fixture->logIn($this->fixture->browser('198.51.100.5'), 'anna@example.com', 'password sbagliata');

        foreach ([$wrongPassword, $unconfirmedWrongPassword, ...array_values($others)] as $response) {
            self::assertSame(422, $response->status);
            self::assertSame([], TestBrowser::setCookieHeaders($response), 'Nessuna sessione, né altri cookie nuovi.');
            self::assertStringContainsString('<li><a href="#email">Email o password non corretti.</a></li>', $response->body);
            self::assertStringContainsString('Ti sei registrato da poco? Prima di accedere conferma l\'email', $response->body);
            self::assertStringNotContainsString('value="password sbagliata"', $response->body);
            self::assertStringNotContainsString('la password di anna', $response->body);
        }
        foreach ($others as $email => $response) {
            self::assertSame(self::withoutCsrf($wrongPassword->body), str_replace($email, AccountFixture::EMAIL, self::withoutCsrf($response->body)), $email);
        }
        self::assertSame(self::withoutCsrf($others['anna@example.com']->body), self::withoutCsrf($unconfirmedWrongPassword->body));
    }

    public function testAFailedLoginOffersANewConfirmationLink(): void
    {
        $this->fixture->register($this->fixture->browser('198.51.100.7'), 'anna@example.com', 'la password di anna');
        $browser = $this->fixture->browser();

        $response = $this->fixture->logIn($browser, 'anna@example.com', 'la password di anna');

        self::assertStringContainsString('<form method="post" action="/account/verifica/nuovo-link/" data-submit-once>', $response->body);
        self::assertStringContainsString('<input type="hidden" name="email" value="anna@example.com">', $response->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm">Mandami il link</button>', $response->body);
        self::assertStringNotContainsString('Mandami il link', $browser->get('/account/accedi/')->body, 'Solo dopo un accesso non riuscito.');

        $resent = $browser->post('/account/verifica/nuovo-link/', ['email' => 'anna@example.com']);
        self::assertSame(303, $resent->status);
        self::assertSame('/account/registrati/controlla-la-posta/', $resent->header('Location'));
        self::assertCount(2, $this->fixture->mailer->sentTo('anna@example.com'));
    }

    public function testEveryAttemptCountsBeforeThePasswordIsChecked(): void
    {
        // I tentativi si contano prima di sapere com'è andata: anche gli accessi riusciti dalla stessa connessione.
        for ($attempt = 1; $attempt <= 20; ++$attempt) {
            self::assertSame(303, $this->fixture->logIn($this->fixture->browser('198.51.100.66'))->status, sprintf('Accesso %d.', $attempt));
        }

        $blocked = $this->fixture->logIn($this->fixture->browser('198.51.100.66'));
        self::assertSame(429, $blocked->status);
        self::assertSame([], TestBrowser::setCookieHeaders($blocked));
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser('198.51.100.67'))->status);
    }

    public function testMissingFieldsAreReported(): void
    {
        $browser = $this->fixture->browser();
        $browser->get('/account/accedi/');

        $response = $browser->post('/account/accedi/', ['email' => '', 'password' => '']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<li><a href="#email">Scrivi la tua email.</a></li>', $response->body);
        self::assertStringContainsString('<li><a href="#password">Scrivi la password.</a></li>', $response->body);
    }

    public function testTooManyWrongPasswordsOnOneAccount(): void
    {
        for ($attempt = 1; $attempt <= 8; ++$attempt) {
            self::assertSame(422, $this->fixture->logIn($this->fixture->browser(sprintf('198.51.100.%d', $attempt)), password: 'sbagliata')->status);
        }

        // Anche con la password giusta, e da un'altra connessione: l'account aspetta.
        $blocked = $this->fixture->logIn($this->fixture->browser('198.51.100.99'));
        self::assertSame(429, $blocked->status);
        self::assertSame('900', $blocked->header('Retry-After'));
        self::assertStringContainsString('Riprova tra 15 minuti.', $blocked->body);

        $this->fixture->clock->travel('+15 minutes');
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser('198.51.100.99'))->status);
    }

    public function testTooManyWrongPasswordsFromOneAddress(): void
    {
        $browser = $this->fixture->browser('198.51.100.66');
        for ($attempt = 1; $attempt <= 20; ++$attempt) {
            self::assertSame(422, $this->fixture->logIn($browser, sprintf('persona%d@example.com', $attempt), 'sbagliata')->status);
        }

        self::assertSame(429, $this->fixture->logIn($browser)->status);
        // Le altre connessioni entrano.
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser('198.51.100.67'))->status);
    }

    public function testASuccessfulLoginClearsTheFailuresOfTheAccount(): void
    {
        for ($attempt = 1; $attempt <= 7; ++$attempt) {
            $this->fixture->logIn($this->fixture->browser(sprintf('198.51.100.%d', $attempt)), password: 'sbagliata');
        }
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser())->status);

        for ($attempt = 1; $attempt <= 7; ++$attempt) {
            self::assertSame(422, $this->fixture->logIn($this->fixture->browser(sprintf('198.51.100.%d', $attempt + 10)), password: 'sbagliata')->status);
        }
    }

    public function testThePasswordHashIsRefreshedWhenTheParametersChange(): void
    {
        $this->fixture->database->execute(
            'UPDATE users SET password_hash = :hash WHERE email = :email',
            ['hash' => password_hash(AccountFixture::PASSWORD, PASSWORD_BCRYPT, ['cost' => 5]), 'email' => AccountFixture::EMAIL],
        );

        self::assertSame(303, $this->fixture->logIn($this->fixture->browser())->status);

        $hash = $this->fixture->database->fetchOne('SELECT password_hash FROM users WHERE email = :email', ['email' => AccountFixture::EMAIL])?->string('password_hash');
        self::assertIsString($hash);
        self::assertStringStartsWith('$2y$04$', $hash);
        self::assertTrue(password_verify(AccountFixture::PASSWORD, $hash));
    }

    public function testTheLinkInTheAlreadyRegisteredEmailLeadsHere(): void
    {
        $this->fixture->register($this->fixture->browser('198.51.100.8'), returnTo: '/prenota/');
        $link = InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/accedi/');

        self::assertSame('/account/accedi/?ritorno=/prenota/', $link);
        self::assertSame(200, $this->fixture->browser()->get($link)->status);
    }

    private static function withoutCsrf(string $html): string
    {
        return (string) preg_replace('/name="csrf" value="[^"]*"/', '', $html);
    }
}
