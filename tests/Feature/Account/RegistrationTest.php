<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Account\AccountModule;
use FourStreet\Account\EmailVerification;
use FourStreet\Account\Http\AccountController;
use FourStreet\Account\Http\CheckEmailController;
use FourStreet\Account\Http\LogoutController;
use FourStreet\Account\Http\RegisterController;
use FourStreet\Account\Http\VerifyEmailController;
use FourStreet\Account\Registration;
use FourStreet\Application;
use FourStreet\Http\Response;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(AccountModule::class)]
#[CoversClass(RegisterController::class)]
#[CoversClass(CheckEmailController::class)]
#[CoversClass(VerifyEmailController::class)]
#[CoversClass(AccountController::class)]
#[CoversClass(LogoutController::class)]
#[CoversClass(Registration::class)]
#[CoversClass(EmailVerification::class)]
final class RegistrationTest extends TestCase
{
    private AccountFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
    }

    public function testTheWholeJourneyFromRegistrationToLogout(): void
    {
        $browser = $this->fixture->browser();

        // Il modulo: campi con autocomplete giusti, token CSRF nel campo nascosto e nel cookie __Host-.
        $form = $browser->get('/account/registrati/');
        self::assertSame(200, $form->status);
        self::assertStringContainsString('<title>4Street · Crea l&#039;account</title>', $form->body);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $form->body);
        self::assertStringContainsString('autocomplete="name"', $form->body);
        self::assertStringContainsString('type="email" value="" autocomplete="email" inputmode="email"', $form->body);
        self::assertStringContainsString('type="password" autocomplete="new-password" required minlength="8" maxlength="128"', $form->body);
        $csrf = $browser->csrfToken();
        self::assertNotNull($csrf);
        self::assertStringContainsString('<input type="hidden" name="csrf" value="' . $csrf . '">', $form->body);

        // L'invio: 303 verso "Controlla la posta", un account non confermato, un'email, e il cookie
        // che riconosce questo browser quando si apre il link.
        $sent = $this->fixture->register($browser);
        self::assertSame(303, $sent->status);
        self::assertSame('/account/registrati/controlla-la-posta/', $sent->header('Location'));
        self::assertSame(1, $this->fixture->count('users'));
        self::assertMatchesRegularExpression(
            '#^__Host-signup=[A-Za-z0-9_-]{43}; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax$#',
            implode("\n", TestBrowser::setCookieHeaders($sent)),
        );
        $check = $browser->follow($sent);
        self::assertSame(200, $check->status);
        self::assertStringContainsString('Controlla la posta.</h1>', $check->body);
        self::assertStringNotContainsString(AccountFixture::EMAIL, $check->body);

        // Il nome non è ancora confermato: non va né nel destinatario né nel saluto.
        $email = $this->fixture->mailer->lastTo(AccountFixture::EMAIL);
        self::assertSame('Conferma la tua email', $email->subject);
        self::assertSame('', $email->to->name);
        self::assertStringContainsString("Ciao,\n\nconferma la tua email per attivare l'account 4Street: apri questo link.", $email->text);
        self::assertStringNotContainsString('Mario', $email->text);
        self::assertStringNotContainsString('Mario', (string) $email->html);
        self::assertStringContainsString('Il link vale 48 ore', $email->text);
        $link = InMemoryMailer::link($email, '/account/verifica/?token=');
        // Il link è assoluto e nasce dall'origine configurata, mai dall'intestazione Host.
        self::assertStringContainsString('https://fourstreet.studio' . $link, $email->text);

        // Prima della conferma l'account non entra.
        self::assertSame(303, $browser->get('/account/')->status);

        // Aperto in questo browser il link conferma, apre la sessione (cookie __Host-, Secure, HttpOnly),
        // cancella il cookie della registrazione e porta alla pagina dell'account.
        $verified = $browser->get($link);
        self::assertSame(303, $verified->status);
        self::assertSame('/account/?avviso=email-confermata', $verified->header('Location'));
        self::assertSame('no-referrer', $verified->header('Referrer-Policy'));
        self::assertMatchesRegularExpression(
            '#^__Host-session=[A-Za-z0-9_-]{43}; Max-Age=2592000; Path=/; Secure; HttpOnly; SameSite=Lax$#',
            implode("\n", array_filter(TestBrowser::setCookieHeaders($verified), static fn(string $line): bool => str_starts_with($line, '__Host-session='))),
        );
        self::assertContains('__Host-signup=; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Lax', TestBrowser::setCookieHeaders($verified));
        self::assertNull($browser->cookie('__Host-signup'));

        $account = $browser->follow($verified);
        self::assertSame(200, $account->status);
        self::assertSame('no-store', $account->header('Cache-Control'));
        self::assertSame('noindex', $account->header('X-Robots-Tag'));
        self::assertStringContainsString('Ciao, Mario Rossi.</h1>', $account->body);
        self::assertStringContainsString('Email confermata. Benvenuto in 4Street.', $account->body);
        self::assertStringContainsString('<dd>mario@example.com</dd>', $account->body);
        self::assertStringContainsString('<dd>Mercoledì 25 novembre 2026</dd>', $account->body);
        self::assertStringContainsString('<h2 id="prenotazioni-titolo" class="fs-label text-fg-subtle">Le tue prenotazioni</h2>', $account->body);
        self::assertStringContainsString('data-bookings', $account->body);

        // L'uscita chiude la sessione e cancella il cookie.
        $logout = $browser->post('/account/esci/');
        self::assertSame(303, $logout->status);
        self::assertSame('/account/accedi/?avviso=uscito', $logout->header('Location'));
        self::assertNull($browser->cookie('__Host-session'));
        self::assertSame(0, $this->fixture->count('sessions'));
        self::assertStringContainsString('Sei uscito dal tuo account.', $browser->follow($logout)->body);
        self::assertSame(303, $browser->get('/account/')->status);
    }

    public function testTheDestinationSurvivesRegistrationEmailAndVerification(): void
    {
        $browser = $this->fixture->browser();

        $sent = $this->fixture->register($browser, returnTo: '/prenota/2026-12-08/?fascia=18%3A30');
        self::assertSame('/account/registrati/controlla-la-posta/?ritorno=/prenota/2026-12-08/%3Ffascia%3D18%253A30', $sent->header('Location'));

        $link = InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/verifica/');
        self::assertStringNotContainsString('prenota', $link, 'La destinazione resta nel database, non nel link.');
        self::assertSame('/prenota/2026-12-08/?fascia=18%3A30', $browser->get($link)->header('Location'));
    }

    public function testTheResponseIsIdenticalWhenTheEmailAlreadyHasAnAccount(): void
    {
        $owner = $this->fixture->browser();
        $this->fixture->registerAndVerify($owner);
        $emailsBefore = count($this->fixture->mailer->sent);

        $stranger = $this->fixture->browser('198.51.100.9');
        $again = $this->fixture->register($stranger, password: 'un\'altra password');
        $newcomer = $this->fixture->register($this->fixture->browser('198.51.100.10'), 'nuova@example.com');

        // Stesso redirect e stessi cookie (a parte il valore casuale) di una registrazione nuova.
        self::assertSame(self::shape($newcomer), self::shape($again));
        self::assertSame(303, $again->status);
        self::assertSame('/account/registrati/controlla-la-posta/', $again->header('Location'));
        self::assertSame(2, $this->fixture->count('users'));
        self::assertCount($emailsBefore + 1, $this->fixture->mailer->sentTo(AccountFixture::EMAIL));

        // L'account è confermato: il suo nome compare.
        $email = $this->fixture->mailer->lastTo(AccountFixture::EMAIL);
        self::assertSame('Hai già un account 4Street', $email->subject);
        self::assertSame('Mario Rossi', $email->to->name);
        self::assertStringContainsString('Ciao Mario Rossi,', $email->text);
        self::assertStringContainsString('L\'account c\'è già', $email->text);
        self::assertSame('/account/accedi/', InMemoryMailer::link($email, '/account/accedi/'));
        self::assertSame('/account/password-dimenticata/', InMemoryMailer::link($email, '/account/password-dimenticata/'));

        // La password dell'account non è cambiata.
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser('192.0.2.1'))->status);
    }

    public function testARegistrationNeverConfirmedIsReplacedByTheNewOne(): void
    {
        $first = $this->fixture->browser('198.51.100.9');
        $this->fixture->register($first, password: 'password di prima', name: 'Prima Persona');
        $firstLink = InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/verifica/');

        $browser = $this->fixture->browser();
        $this->fixture->register($browser);
        $secondLink = InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/verifica/');

        self::assertSame(1, $this->fixture->count('users'));
        self::assertNotSame($firstLink, $secondLink);
        // Il link della prima registrazione non vale più. Quello nuovo basta da solo solo nel browser della
        // seconda registrazione: il primo browser deve scrivere la password, che ora non è più la sua.
        self::assertSame(404, $first->get($firstLink)->status);
        self::assertStringContainsString('Conferma l&#039;email.</h1>', $first->get($secondLink)->body);
        self::assertSame(303, $browser->get($secondLink)->status);
        self::assertStringContainsString('Ciao, Mario Rossi.', $browser->get('/account/')->body);
        self::assertSame(422, $this->fixture->logIn($this->fixture->browser('192.0.2.6'), password: 'password di prima')->status);
    }

    public function testInvalidFieldsAreReportedAccessiblyAndRefilledExceptThePassword(): void
    {
        $browser = $this->fixture->browser();
        $browser->get('/account/registrati/');

        $response = $browser->post('/account/registrati/', ['nome' => ' ', 'email' => 'mario@', 'password' => 'corta']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<title>Errore: 4Street · Crea l&#039;account</title>', $response->body);
        self::assertStringContainsString('<div class="fs-alert fs-alert--error" role="alert" tabindex="-1" aria-labelledby="riepilogo-errori" data-error-summary>', $response->body);
        foreach ([
            '<li><a href="#nome">Scrivi nome e cognome.</a></li>',
            '<li><a href="#email">Scrivi un indirizzo email valido, come nome@esempio.it.</a></li>',
            '<li><a href="#password">La password deve avere almeno 8 caratteri.</a></li>',
            '<li><a href="#privacy">Per creare l&#039;account conferma di aver letto l&#039;informativa sulla privacy.</a></li>',
        ] as $item) {
            self::assertStringContainsString($item, $response->body);
        }
        self::assertStringContainsString('id="email" name="email" type="email" value="mario@"', $response->body);
        self::assertStringContainsString('aria-invalid="true" aria-describedby="email-aiuto email-errore"', $response->body);
        self::assertStringContainsString('<p class="fs-field__error" id="password-errore">', $response->body);
        self::assertStringNotContainsString('value="corta"', $response->body);
        self::assertSame(0, $this->fixture->count('users'));
        self::assertSame([], $this->fixture->mailer->sent);
    }

    public function testThePrivacyLinkOrItsPlaceholder(): void
    {
        $withPlaceholder = $this->fixture->browser()->get('/account/registrati/')->body;
        self::assertStringContainsString('<span class="fs-check__text">Ho letto l\'informativa sulla privacy. <span class="fs-ph">[Informativa privacy]</span></span>', $withPlaceholder);

        $withLink = (new AccountFixture(site: ['content' => ['privacy_url' => 'https://fourstreet.studio/privacy/']]))->browser()->get('/account/registrati/')->body;
        self::assertStringContainsString('Ho letto l\'<a class="fs-text-link" href="https://fourstreet.studio/privacy/" target="_blank" rel="noopener">informativa sulla privacy<span class="sr-only"> (si apre in una nuova scheda)</span></a>.', $withLink);

        $withoutPlaceholders = (new AccountFixture(site: ['show_placeholders' => false]))->browser()->get('/account/registrati/')->body;
        self::assertStringContainsString('<span class="fs-check__text">Ho letto l&#039;informativa sulla privacy.</span>', $withoutPlaceholders);
    }

    public function testAFailedEmailIsNeverAnErrorPage(): void
    {
        $this->fixture->mailer->failing = true;
        $browser = $this->fixture->browser();

        $sent = $this->fixture->register($browser);

        self::assertSame(303, $sent->status);
        self::assertSame('/account/registrati/controlla-la-posta/?avviso=email-non-inviata', $sent->header('Location'));
        self::assertNotNull($browser->cookie('__Host-signup'), 'Il link nuovo, da qui, varrà da solo.');
        self::assertCount(1, $this->fixture->reporter->reported);
        $page = $browser->follow($sent);
        self::assertSame(200, $page->status);
        self::assertStringContainsString('Non siamo riusciti a mandarti l&#039;email.', $page->body);
        self::assertStringContainsString('href="/account/verifica/nuovo-link/"', $page->body);
    }

    public function testTooManyRegistrationsFromTheSameAddress(): void
    {
        $browser = $this->fixture->browser();
        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            self::assertSame(303, $this->fixture->register($browser, sprintf('persona%d@example.com', $attempt))->status);
        }

        $blocked = $this->fixture->register($browser, 'persona11@example.com');

        self::assertSame(429, $blocked->status);
        self::assertNotNull($blocked->header('Retry-After'));
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra', $blocked->body);
        self::assertSame(10, $this->fixture->count('users'));
        // Da un'altra connessione si registra ancora.
        self::assertSame(303, $this->fixture->register($this->fixture->browser('198.51.100.20'), 'persona11@example.com')->status);
    }

    public function testNoMoreThanFiveEmailsAnHourToTheSameAddress(): void
    {
        for ($attempt = 1; $attempt <= 7; ++$attempt) {
            $response = $this->fixture->register($this->fixture->browser(sprintf('198.51.100.%d', $attempt)));
            self::assertSame(303, $response->status);
            self::assertSame('/account/registrati/controlla-la-posta/', $response->header('Location'));
        }

        self::assertCount(5, $this->fixture->mailer->sentTo(AccountFixture::EMAIL));

        $this->fixture->clock->travel('+1 hour');
        $this->fixture->register($this->fixture->browser('198.51.100.50'));
        self::assertCount(6, $this->fixture->mailer->sentTo(AccountFixture::EMAIL));
    }

    public function testALoggedInVisitorGoesStraightToTheAccount(): void
    {
        $browser = $this->fixture->browser();
        $this->fixture->registerAndVerify($browser);

        self::assertSame('/account/', $browser->get('/account/registrati/')->header('Location'));
        self::assertSame('/account/', $browser->get('/account/accedi/')->header('Location'));
    }

    /**
     * Stato, destinazione e cookie di una risposta, con i valori casuali dei cookie nascosti.
     *
     * @return array{int, string|null, list<string>}
     */
    private static function shape(Response $response): array
    {
        $cookies = array_map(static fn(string $line): string => (string) preg_replace('/=[A-Za-z0-9_-]{43};/', '=…;', $line), TestBrowser::setCookieHeaders($response));

        return [$response->status, $response->header('Location'), $cookies];
    }
}
