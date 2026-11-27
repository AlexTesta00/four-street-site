<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Account\Http\ForgotPasswordController;
use FourStreet\Account\Http\ResetPasswordController;
use FourStreet\Account\PasswordReset;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\Token\AccountTokens;
use FourStreet\Account\Token\PdoAccountTokenRepository;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Application;
use FourStreet\Http\Response;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(ForgotPasswordController::class)]
#[CoversClass(ResetPasswordController::class)]
#[CoversClass(PasswordReset::class)]
final class PasswordResetTest extends TestCase
{
    private const string NEW_PASSWORD = 'la mia password nuova';

    private AccountFixture $fixture;

    private TestBrowser $phone;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        // L'account è aperto anche su un altro dispositivo.
        $this->phone = $this->fixture->browser('192.0.2.200');
        $this->fixture->registerAndVerify($this->phone);
    }

    public function testTheWholeResetClosesEverySessionAndWarnsByEmail(): void
    {
        $browser = $this->fixture->browser();
        $form = $browser->get('/account/password-dimenticata/');
        self::assertSame(200, $form->status);
        self::assertStringContainsString('Password dimenticata.</h1>', $form->body);

        $sent = $browser->post('/account/password-dimenticata/', ['email' => AccountFixture::EMAIL]);
        self::assertSame(303, $sent->status);
        self::assertSame('/account/password-dimenticata/controlla-la-posta/', $sent->header('Location'));
        self::assertStringContainsString('Se l&#039;indirizzo corrisponde a un account', $browser->follow($sent)->body);

        $email = $this->fixture->mailer->lastTo(AccountFixture::EMAIL);
        self::assertSame('Scegli una nuova password', $email->subject);
        self::assertSame('Mario Rossi', $email->to->name);
        self::assertStringContainsString('Ciao Mario Rossi,', $email->text);
        $link = InMemoryMailer::link($email, '/account/nuova-password/?token=');

        $page = $browser->get($link);
        self::assertSame(200, $page->status);
        self::assertSame('no-referrer', $page->header('Referrer-Policy'));
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('Scegli la nuova password per mario@example.com.', $page->body);
        self::assertStringContainsString('<input type="email" autocomplete="username" value="mario@example.com" hidden readonly>', $page->body);
        self::assertStringContainsString('type="password" autocomplete="new-password"', $page->body);
        $token = substr($link, strlen('/account/nuova-password/?token='));
        self::assertStringContainsString('<input type="hidden" name="token" value="' . $token . '">', $page->body);

        $changed = $browser->post('/account/nuova-password/', ['token' => $token, 'password' => self::NEW_PASSWORD]);
        self::assertSame(303, $changed->status);
        self::assertSame('/account/?avviso=password-aggiornata', $changed->header('Location'));
        self::assertStringContainsString('Password aggiornata.', $browser->follow($changed)->body);

        // L'altro dispositivo è fuori, la password vecchia non vale più, quella nuova sì.
        self::assertSame(303, $this->phone->get('/account/')->status);
        self::assertSame(1, $this->fixture->count('sessions'));
        self::assertSame(422, $this->fixture->logIn($this->fixture->browser('198.51.100.1'))->status);
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser('198.51.100.2'), password: self::NEW_PASSWORD)->status);

        $warning = $this->fixture->mailer->lastTo(AccountFixture::EMAIL);
        self::assertSame('La tua password è cambiata', $warning->subject);
        self::assertStringContainsString('Ciao Mario Rossi,', $warning->text);
        self::assertStringContainsString('è stata cambiata mercoledì 25 novembre 2026 alle 08:00.', $warning->text);
        self::assertSame('/account/password-dimenticata/', InMemoryMailer::link($warning, '/account/password-dimenticata/'));
    }

    public function testTheLinkWorksOnlyOnce(): void
    {
        $token = $this->requestToken();
        self::assertSame(303, $this->submitAfterVisiting($this->fixture->browser(), $token)->status);

        $again = $this->fixture->browser('198.51.100.4')->get('/account/nuova-password/?token=' . $token);

        self::assertSame(410, $again->status);
        self::assertStringContainsString('Il link non vale più.</h1>', $again->body);
        self::assertStringContainsString('href="/account/password-dimenticata/"', $again->body);
    }

    public function testTheLinkExpiresAfterAnHour(): void
    {
        $token = $this->requestToken();
        $this->fixture->clock->travel('+1 hour');

        self::assertSame(410, $this->fixture->browser()->get('/account/nuova-password/?token=' . $token)->status);
    }

    public function testOnlyTheLastLinkWorks(): void
    {
        $first = $this->requestToken();
        $second = $this->requestToken();

        self::assertSame(410, $this->fixture->browser()->get('/account/nuova-password/?token=' . $first)->status);
        self::assertSame(200, $this->fixture->browser()->get('/account/nuova-password/?token=' . $second)->status);
    }

    public function testTheNewPasswordFollowsTheRules(): void
    {
        $token = $this->requestToken();
        $browser = $this->fixture->browser();
        $browser->get('/account/nuova-password/?token=' . $token);

        $tooShort = $browser->post('/account/nuova-password/', ['token' => $token, 'password' => 'corta']);
        $tooLongForBcrypt = $browser->post('/account/nuova-password/', ['token' => $token, 'password' => str_repeat('è', 40)]);

        self::assertSame(422, $tooShort->status);
        self::assertStringContainsString('La password deve avere almeno 8 caratteri.', $tooShort->body);
        self::assertSame(422, $tooLongForBcrypt->status);
        self::assertStringContainsString('La password è troppo lunga: accorciala un po&#039;.', $tooLongForBcrypt->body);
        // Il link vale ancora: gli errori non lo consumano.
        self::assertSame(200, $this->fixture->browser()->get('/account/nuova-password/?token=' . $token)->status);
    }

    public function testTheRequestIsAnsweredTheSameWayForAnyAddress(): void
    {
        $this->fixture->register($this->fixture->browser('198.51.100.30'), 'anna@example.com', 'la password di anna');
        $emails = count($this->fixture->mailer->sent);

        $browser = $this->fixture->browser();
        $browser->get('/account/password-dimenticata/');
        $unknown = $browser->post('/account/password-dimenticata/', ['email' => 'nessuno@example.com']);
        $unconfirmed = $browser->post('/account/password-dimenticata/', ['email' => 'anna@example.com']);
        $known = $browser->post('/account/password-dimenticata/', ['email' => AccountFixture::EMAIL]);

        foreach ([$unknown, $unconfirmed, $known] as $response) {
            self::assertSame(303, $response->status);
            self::assertSame('/account/password-dimenticata/controlla-la-posta/', $response->header('Location'));
            self::assertSame([], TestBrowser::setCookieHeaders($response));
            self::assertSame($unknown->headers(), $response->headers());
        }
        // Cambia solo l'email nella casella: il link per l'account confermato, la registrazione da rifare per quello in sospeso.
        self::assertCount($emails + 2, $this->fixture->mailer->sent);
        self::assertSame('Scegli una nuova password', $this->fixture->mailer->lastTo(AccountFixture::EMAIL)->subject);
        self::assertSame('Completa la registrazione', $this->fixture->mailer->lastTo('anna@example.com')->subject);
    }

    /**
     * Il tetto delle email (5 l'ora per indirizzo) si consuma anche per un indirizzo senza account: altrimenti
     * chi lo esaurisce qui e poi si registra con quell'email capirebbe dal tempo di risposta della registrazione
     * (che manda subito, oppure no) se l'account c'è.
     */
    public function testEveryRequestCountsInTheEmailCapEvenWithoutAnAccount(): void
    {
        $browser = $this->fixture->browser('198.51.100.30');
        $browser->get('/account/password-dimenticata/');
        for ($request = 1; $request <= 5; ++$request) {
            self::assertSame(303, $browser->post('/account/password-dimenticata/', ['email' => 'nessuno@example.com'])->status);
        }

        $registered = $this->fixture->register($this->fixture->browser('198.51.100.31'), 'nessuno@example.com');

        // Come per un indirizzo con l'account e il tetto esaurito: la stessa pagina, e non parte nulla.
        self::assertSame('/account/registrati/controlla-la-posta/', $registered->header('Location'));
        self::assertSame([], $this->fixture->mailer->sentTo('nessuno@example.com'));
        self::assertSame(1, $this->fixture->count('users'), 'Oltre il tetto la registrazione si ferma prima di creare l\'account.');
    }

    public function testAFailedRequestLooksLikeASentOne(): void
    {
        $this->fixture->mailer->failing = true;
        $browser = $this->fixture->browser();
        $browser->get('/account/password-dimenticata/');

        $known = $browser->post('/account/password-dimenticata/', ['email' => AccountFixture::EMAIL]);
        $unknown = $browser->post('/account/password-dimenticata/', ['email' => 'nessuno@example.com']);

        // L'email parte dopo la risposta: un invio fallito non cambia la pagina, e così non rivela l'account.
        self::assertSame($unknown->header('Location'), $known->header('Location'));
        self::assertSame('/account/password-dimenticata/controlla-la-posta/', $known->header('Location'));
        self::assertCount(1, $this->fixture->reporter->reported, 'Il problema resta nel log.');
        self::assertStringNotContainsString('Non siamo riusciti', $browser->follow($known)->body);
    }

    /**
     * Un account mai confermato non riceve il link per la nuova password: ha il nome, la password e il consenso
     * di chi ha compilato la registrazione, magari un estraneo, e la password nuova li farebbe propri. L'email
     * dice che la registrazione non è completa e porta alla registrazione, che prende il posto di quella in sospeso.
     */
    public function testAnUnconfirmedAccountIsSentToRegisterAgain(): void
    {
        $this->fixture->register($this->fixture->browser('198.51.100.30'), 'anna@example.com', 'la password di un estraneo', 'Nome Estraneo');
        $browser = $this->fixture->browser();
        $browser->get('/account/password-dimenticata/?ritorno=/prenota/');
        $sent = $browser->post('/account/password-dimenticata/', ['email' => 'anna@example.com', 'ritorno' => '/prenota/']);
        self::assertSame('/account/password-dimenticata/controlla-la-posta/?ritorno=/prenota/', $sent->header('Location'));

        $email = $this->fixture->mailer->lastTo('anna@example.com');
        self::assertSame('Completa la registrazione', $email->subject);
        // Il nome l'ha scritto chi ha compilato la registrazione: nelle email non c'è.
        self::assertSame('', $email->to->name);
        self::assertStringContainsString("Ciao,\n", $email->text);
        self::assertStringNotContainsString('Nome Estraneo', $email->text);
        self::assertStringNotContainsString('Nome Estraneo', (string) $email->html);
        self::assertStringNotContainsString('/account/nuova-password/', $email->text);
        $link = InMemoryMailer::link($email, '/account/registrati/');
        self::assertSame('/account/registrati/?ritorno=/prenota/', $link);

        // La registrazione nuova sostituisce quella in sospeso: valgono il nome e la password nuovi.
        self::assertSame(200, $browser->get($link)->status);
        $this->fixture->register($browser, 'anna@example.com', self::NEW_PASSWORD, 'Anna Bianchi', '/prenota/');
        self::assertSame('/prenota/', $browser->get(InMemoryMailer::link($this->fixture->mailer->lastTo('anna@example.com'), '/account/verifica/'))->header('Location'));
        self::assertSame('Anna Bianchi', $this->fixture->user('anna@example.com')->fullName);
        self::assertSame(422, $this->fixture->logIn($this->fixture->browser('198.51.100.31'), 'anna@example.com', 'la password di un estraneo')->status);
    }

    public function testAResetLinkNeverWorksForAnUnconfirmedAccount(): void
    {
        // Un link chiesto prima di questa regola: non cambia la password di un account in sospeso.
        $this->fixture->register($this->fixture->browser('198.51.100.30'), 'anna@example.com', 'la password di anna');
        $token = (new AccountTokens(new PdoAccountTokenRepository($this->fixture->database), $this->fixture->clock))
            ->issue($this->fixture->user('anna@example.com'), TokenPurpose::PasswordReset, ReturnPath::default());
        $browser = $this->fixture->browser();

        self::assertSame(410, $browser->get('/account/nuova-password/?token=' . $token->value)->status);
        self::assertSame(410, $browser->post('/account/nuova-password/', ['token' => $token->value, 'password' => self::NEW_PASSWORD])->status);
        self::assertSame(422, $this->fixture->logIn($this->fixture->browser('198.51.100.31'), 'anna@example.com', self::NEW_PASSWORD)->status);
    }

    public function testTheDestinationSurvivesTheReset(): void
    {
        $browser = $this->fixture->browser();
        $form = $browser->get('/account/password-dimenticata/?ritorno=/prenota/');
        self::assertStringContainsString('<input type="hidden" name="ritorno" value="/prenota/">', $form->body);
        $browser->post('/account/password-dimenticata/', ['email' => AccountFixture::EMAIL, 'ritorno' => '/prenota/']);
        $link = InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/nuova-password/');
        $browser->get($link);

        $changed = $browser->post('/account/nuova-password/', ['token' => substr($link, strlen('/account/nuova-password/?token=')), 'password' => self::NEW_PASSWORD]);

        self::assertSame('/prenota/', $changed->header('Location'));
    }

    public function testAFailedWarningEmailDoesNotUndoTheChange(): void
    {
        $token = $this->requestToken();
        $this->fixture->mailer->failing = true;

        $changed = $this->submitAfterVisiting($this->fixture->browser(), $token);

        self::assertSame(303, $changed->status);
        self::assertCount(1, $this->fixture->reporter->reported);
        self::assertSame(303, $this->fixture->logIn($this->fixture->browser('198.51.100.5'), password: self::NEW_PASSWORD)->status);
    }

    /** Chiede il link per la nuova password e restituisce il suo token. */
    private function requestToken(): string
    {
        $browser = $this->fixture->browser('198.51.100.40');
        $browser->get('/account/password-dimenticata/');
        $browser->post('/account/password-dimenticata/', ['email' => AccountFixture::EMAIL]);
        $link = InMemoryMailer::link($this->fixture->mailer->lastTo(AccountFixture::EMAIL), '/account/nuova-password/?token=');

        return substr($link, strlen('/account/nuova-password/?token='));
    }

    private function submitAfterVisiting(TestBrowser $browser, string $token): Response
    {
        $browser->get('/account/nuova-password/?token=' . $token);

        return $browser->post('/account/nuova-password/', ['token' => $token, 'password' => self::NEW_PASSWORD]);
    }
}
