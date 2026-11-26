<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Account\EmailVerification;
use FourStreet\Account\Form\ConfirmEmailForm;
use FourStreet\Account\Http\ResendVerificationController;
use FourStreet\Account\Http\SignupBrowser;
use FourStreet\Account\Http\VerifyEmailController;
use FourStreet\Application;
use FourStreet\Http\Response;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(VerifyEmailController::class)]
#[CoversClass(ResendVerificationController::class)]
#[CoversClass(EmailVerification::class)]
#[CoversClass(SignupBrowser::class)]
#[CoversClass(ConfirmEmailForm::class)]
final class EmailVerificationTest extends TestCase
{
    private AccountFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
    }

    public function testTheLinkAloneIsEnoughOnlyInTheBrowserThatRegistered(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->register($browser);
        self::assertNotNull($browser->cookie('__Host-signup'));

        $verified = $browser->get($this->link());

        self::assertSame(303, $verified->status);
        self::assertSame('/account/?avviso=email-confermata', $verified->header('Location'));
        self::assertNotNull($browser->cookie('__Host-session'));
        self::assertNull($browser->cookie('__Host-signup'), 'Il cookie della registrazione non serve più.');
        self::assertSame(1, $this->fixture->count('sessions'));
    }

    /**
     * Un altro dispositivo, il browser dell'app di posta, un programma che controlla i link: il link mostra
     * la pagina della password e non cambia nulla. Chiunque può registrare un indirizzo non suo: se il link
     * bastasse, il destinatario che lo apre attiverebbe un account con la password di un altro.
     */
    public function testElsewhereTheLinkAsksForThePasswordAndChangesNothing(): void
    {
        $this->fixture->register($this->fixture->browser(), returnTo: '/prenota/');
        $link = $this->link();
        $elsewhere = $this->fixture->browser('198.51.100.1');

        $page = $elsewhere->get($link);

        self::assertSame(200, $page->status);
        self::assertSame('no-referrer', $page->header('Referrer-Policy'));
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('Conferma l&#039;email.</h1>', $page->body);
        self::assertStringContainsString('Per attivare l&#039;account di mario@example.com scrivi la password che hai scelto registrandoti.', $page->body);
        self::assertStringContainsString('<form method="post" action="/account/verifica/" class="fs-form" novalidate data-submit-once>', $page->body);
        self::assertStringContainsString('<input type="hidden" name="token" value="' . self::token($link) . '">', $page->body);
        self::assertStringContainsString('<input type="email" autocomplete="username" value="mario@example.com" hidden readonly>', $page->body);
        self::assertStringContainsString('type="password" autocomplete="current-password" required maxlength="128"', $page->body);
        self::assertStringContainsString('href="/account/password-dimenticata/?ritorno=/prenota/">Password dimenticata?</a>', $page->body);
        self::assertStringContainsString('Non hai creato tu questo account?', $page->body);
        self::assertNull($elsewhere->cookie('__Host-session'));
        self::assertSame(0, $this->fixture->count('sessions'));
        self::assertFalse($this->isVerified());
        self::assertSame(422, $this->fixture->logIn($this->fixture->browser('198.51.100.2'))->status, 'Senza conferma non si entra.');
        self::assertSame(200, $elsewhere->get($link)->status, 'Il link vale ancora.');
    }

    public function testElsewhereThePasswordConfirmsAndLogsIn(): void
    {
        $registering = $this->fixture->browser();
        $this->fixture->register($registering, returnTo: '/prenota/');
        $link = $this->link();
        $phone = $this->fixture->browser('198.51.100.1');
        $phone->get($link);

        $confirmed = $phone->post('/account/verifica/', ['token' => self::token($link), 'password' => AccountFixture::PASSWORD]);

        self::assertSame(303, $confirmed->status);
        self::assertSame('/prenota/', $confirmed->header('Location'));
        self::assertNotNull($phone->cookie('__Host-session'));
        self::assertTrue($this->isVerified());
        self::assertSame(200, $phone->get('/account/')->status);
        // Il link è usato: anche nel browser della registrazione ora si accede con la password.
        self::assertStringContainsString('Email già confermata.</h1>', $registering->get($link)->body);
    }

    public function testAWrongOrMissingPasswordKeepsTheLinkValid(): void
    {
        $this->fixture->register($this->fixture->browser());
        $token = self::token($this->link());
        $elsewhere = $this->fixture->browser('198.51.100.1');
        $elsewhere->get($this->link());

        $wrong = $elsewhere->post('/account/verifica/', ['token' => $token, 'password' => 'non è questa']);
        self::assertSame(422, $wrong->status);
        self::assertStringContainsString('<title>Errore: 4Street · Conferma l&#039;email</title>', $wrong->body);
        self::assertStringContainsString('<li><a href="#password">Password non corretta.</a></li>', $wrong->body);
        self::assertStringContainsString('<input type="hidden" name="token" value="' . $token . '">', $wrong->body);
        self::assertStringNotContainsString('non è questa', $wrong->body);

        $missing = $elsewhere->post('/account/verifica/', ['token' => $token]);
        self::assertSame(422, $missing->status);
        self::assertStringContainsString('<li><a href="#password">Scrivi la password che hai scelto registrandoti.</a></li>', $missing->body);

        self::assertFalse($this->isVerified());
        self::assertSame(303, $elsewhere->post('/account/verifica/', ['token' => $token, 'password' => AccountFixture::PASSWORD])->status);
    }

    public function testPasswordAttemptsOnTheLinkAreLimitedLikeLogins(): void
    {
        $this->fixture->register($this->fixture->browser());
        $link = $this->link();
        for ($attempt = 1; $attempt <= 8; ++$attempt) {
            self::assertSame(422, $this->confirmFrom($this->fixture->browser(sprintf('198.51.100.%d', $attempt)), $link, 'sbagliata')->status);
        }

        // Anche con la password giusta, e da un'altra connessione: l'account aspetta, come per l'accesso.
        $blocked = $this->confirmFrom($this->fixture->browser('198.51.100.99'), $link, AccountFixture::PASSWORD);
        self::assertSame(429, $blocked->status);
        self::assertSame('900', $blocked->header('Retry-After'));
        self::assertStringContainsString('Riprova tra 15 minuti.', $blocked->body);
        self::assertStringContainsString('<input type="hidden" name="token" value="' . self::token($link) . '">', $blocked->body);
        self::assertFalse($this->isVerified());
        self::assertSame(429, $this->fixture->logIn($this->fixture->browser('198.51.100.98'))->status, 'Il contatore è quello degli accessi.');

        $this->fixture->clock->travel('+15 minutes');
        self::assertSame(303, $this->confirmFrom($this->fixture->browser('198.51.100.99'), $link, AccountFixture::PASSWORD)->status);
    }

    public function testTheLinkOfARegistrationMadeBySomeoneElseActivatesNothing(): void
    {
        // Qualcuno registra l'indirizzo di Mario con una password sua.
        $this->fixture->register($this->fixture->browser('198.51.100.66'), password: 'la password di un altro');
        $owner = $this->fixture->browser();

        // Mario apre il link: senza quella password non può attivare l'account, e nemmeno l'altro può entrare.
        self::assertSame(200, $owner->get($this->link())->status);
        self::assertFalse($this->isVerified());
        self::assertSame(422, $this->fixture->logIn($this->fixture->browser('198.51.100.67'), password: 'la password di un altro')->status);

        // Mario si registra con la sua password: la registrazione in sospeso è sostituita, e il link nuovo,
        // nel suo browser, lo fa entrare.
        $this->fixture->register($owner);
        self::assertSame(303, $owner->get($this->link())->status);
        self::assertSame(200, $owner->get('/account/')->status);
        self::assertSame(422, $this->fixture->logIn($this->fixture->browser('198.51.100.68'), password: 'la password di un altro')->status);
    }

    public function testAnExpiredLinkAsksForANewOne(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->register($browser);
        $link = $this->link();

        $this->fixture->clock->travel('+48 hours');
        $response = $browser->get($link);

        self::assertSame(410, $response->status);
        self::assertStringContainsString('Il link è scaduto.</h1>', $response->body);
        self::assertStringContainsString('href="/account/verifica/nuovo-link/"', $response->body);
        self::assertSame('no-referrer', $response->header('Referrer-Policy'));
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame(0, $this->fixture->count('sessions'));
        self::assertSame(410, $this->confirmFrom($this->fixture->browser('198.51.100.1'), $link, AccountFixture::PASSWORD)->status);
    }

    public function testOneSecondBeforeExpiryTheLinkStillWorks(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->register($browser);
        $link = $this->link();

        $this->fixture->clock->travel('+48 hours -1 second');

        self::assertSame(303, $browser->get($link)->status);
    }

    public function testAUsedLinkOnAConfirmedAccountSendsToTheLoginPage(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->register($browser);
        $link = $this->link();
        self::assertSame(303, $browser->get($link)->status);

        $response = $this->fixture->browser('198.51.100.1')->get($link);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Email già confermata.</h1>', $response->body);
        self::assertStringContainsString('<a href="/account/accedi/" class="fs-btn fs-btn--primary fs-btn--block">Accedi</a>', $response->body);
        self::assertSame(1, $this->fixture->count('sessions'));
        self::assertSame('/account/', $browser->get($link)->header('Location'), 'Chi è già dentro va alla pagina dell\'account.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLinks(): iterable
    {
        yield 'senza token' => ['/account/verifica/'];
        yield 'token vuoto' => ['/account/verifica/?token='];
        yield 'token troncato' => ['/account/verifica/?token=abc'];
        yield 'token sconosciuto' => ['/account/verifica/?token=' . str_repeat('A', 43)];
        yield 'token come array' => ['/account/verifica/?token[]=x'];
    }

    #[DataProvider('invalidLinks')]
    public function testAnInvalidLinkIsExplained(string $uri): void
    {
        $response = $this->fixture->browser()->get($uri);

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Link non valido.</h1>', $response->body);
        self::assertStringContainsString('Mandami un nuovo link', $response->body);
    }

    public function testAPasswordWithoutAValidTokenIsTheInvalidLinkPage(): void
    {
        $this->fixture->register($this->fixture->browser());
        $browser = $this->fixture->browser('198.51.100.1');
        $browser->get('/account/verifica/');

        $response = $browser->post('/account/verifica/', ['token' => str_repeat('A', 43), 'password' => AccountFixture::PASSWORD]);

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Link non valido.</h1>', $response->body);
        self::assertFalse($this->isVerified());
    }

    public function testHeadDoesNotConsumeTheLink(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->register($browser);
        $link = $this->link();

        $head = $browser->head($link);

        self::assertSame(200, $head->status);
        self::assertSame('', $head->body);
        self::assertSame([], TestBrowser::setCookieHeaders($head));
        self::assertSame(303, $browser->get($link)->status);
    }

    public function testANewLinkReplacesTheOldOneAndKeepsTheShortcutOfTheRegisteringBrowser(): void
    {
        $registering = $this->fixture->browser();
        $this->fixture->register($registering);
        $oldLink = $this->link();

        // Il link nuovo lo chiede un altro browser (chiunque può chiederlo): resta valido da solo solo dove ci si è registrati.
        $other = $this->fixture->browser('198.51.100.1');
        $form = $other->get('/account/verifica/nuovo-link/');
        self::assertSame(200, $form->status);
        self::assertStringContainsString('Nuovo link di conferma.</h1>', $form->body);
        $sent = $other->post('/account/verifica/nuovo-link/', ['email' => 'Mario@Example.com ']);

        self::assertSame(303, $sent->status);
        self::assertSame('/account/registrati/controlla-la-posta/', $sent->header('Location'));
        $newLink = $this->link();
        self::assertNotSame($oldLink, $newLink);
        self::assertSame(404, $registering->get($oldLink)->status);
        self::assertSame(200, $other->get($newLink)->status, 'Altrove chiede la password.');
        self::assertSame(303, $registering->get($newLink)->status);
    }

    public function testANewLinkIsAnsweredTheSameWayForAnyAddress(): void
    {
        $this->fixture->registerAndVerify($this->fixture->browser());
        $this->fixture->register($this->fixture->browser('198.51.100.5'), 'anna@example.com', 'la password di anna');
        $emails = count($this->fixture->mailer->sent);

        $responses = [];
        foreach (['nessuno@example.com', AccountFixture::EMAIL, 'anna@example.com'] as $email) {
            $browser = $this->fixture->browser();
            $browser->get('/account/verifica/nuovo-link/');
            $responses[$email] = $browser->post('/account/verifica/nuovo-link/', ['email' => $email]);
        }

        foreach ($responses as $response) {
            self::assertSame(303, $response->status);
            self::assertSame('/account/registrati/controlla-la-posta/', $response->header('Location'));
            self::assertSame([], TestBrowser::setCookieHeaders($response));
        }
        // Parte solo il link per l'account da confermare.
        self::assertCount($emails + 1, $this->fixture->mailer->sent);
        self::assertSame('Conferma la tua email', $this->fixture->mailer->lastTo('anna@example.com')->subject);
    }

    public function testAFailedNewLinkLooksLikeASentOne(): void
    {
        $this->fixture->register($this->fixture->browser());
        $this->fixture->mailer->failing = true;
        $browser = $this->fixture->browser();
        $browser->get('/account/verifica/nuovo-link/');

        $response = $browser->post('/account/verifica/nuovo-link/', ['email' => AccountFixture::EMAIL]);

        // L'email parte dopo la risposta: la pagina è quella di sempre, il problema resta nel log.
        self::assertSame(303, $response->status);
        self::assertSame('/account/registrati/controlla-la-posta/', $response->header('Location'));
        self::assertCount(1, $this->fixture->reporter->reported);
        self::assertStringNotContainsString('Non siamo riusciti', $browser->follow($response)->body);
    }

    public function testTheNewLinkFormValidatesTheAddress(): void
    {
        $browser = $this->fixture->browser();
        $browser->get('/account/verifica/nuovo-link/');

        $response = $browser->post('/account/verifica/nuovo-link/', ['email' => 'non è un indirizzo']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<li><a href="#email">Scrivi un indirizzo email valido, come nome@esempio.it.</a></li>', $response->body);
        self::assertStringContainsString('value="non è un indirizzo"', $response->body);
    }

    public function testTheNewLinkKeepsTheDestination(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->register($browser);
        $form = $browser->get('/account/verifica/nuovo-link/?ritorno=/prenota/');
        self::assertStringContainsString('<input type="hidden" name="ritorno" value="/prenota/">', $form->body);

        $sent = $browser->post('/account/verifica/nuovo-link/', ['email' => AccountFixture::EMAIL, 'ritorno' => '/prenota/']);

        self::assertSame('/account/registrati/controlla-la-posta/?ritorno=/prenota/', $sent->header('Location'));
        self::assertSame('/prenota/', $browser->get($this->link())->header('Location'));
    }

    public function testTooManyRequestsForANewLink(): void
    {
        $browser = $this->fixture->browser();
        $browser->get('/account/verifica/nuovo-link/');
        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            self::assertSame(303, $browser->post('/account/verifica/nuovo-link/', ['email' => sprintf('n%d@example.com', $attempt)])->status);
        }

        $blocked = $browser->post('/account/verifica/nuovo-link/', ['email' => 'n11@example.com']);

        self::assertSame(429, $blocked->status);
        self::assertStringContainsString('Troppi tentativi in poco tempo.', $blocked->body);
    }

    /** Il link dell'ultima email di conferma per Mario. */
    private function link(): string
    {
        return InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/verifica/');
    }

    /** Come farebbe una persona da un altro browser: apre il link, poi invia la password dalla sua pagina. */
    private function confirmFrom(TestBrowser $browser, string $link, string $password): Response
    {
        $browser->get($link);

        return $browser->post('/account/verifica/', ['token' => self::token($link), 'password' => $password]);
    }

    /** Il token di un link di conferma, come lo rimette nel modulo la pagina della password. */
    private static function token(string $link): string
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        self::assertIsString($query['token'] ?? null);

        return $query['token'];
    }

    private function isVerified(string $email = AccountFixture::EMAIL): bool
    {
        $row = $this->fixture->database->fetchOne('SELECT email_verified_at FROM users WHERE email = :email', ['email' => $email]);
        self::assertNotNull($row);

        return $row->nullableString('email_verified_at') !== null;
    }
}
