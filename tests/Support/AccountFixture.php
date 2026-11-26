<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Application;
use FourStreet\Backend;
use FourStreet\Database\Database;
use FourStreet\Http\Origin;
use FourStreet\Http\Response;
use FourStreet\Security\Password\NativePasswordHasher;
use PHPUnit\Framework\Assert;

/**
 * L'applicazione vera con l'area clienti attiva: database dei test (SQLite in memoria), posta in memoria,
 * bcrypt con il costo minimo e un orologio che il test può spostare.
 */
final class AccountFixture
{
    public const string NOW = '2026-11-25 08:00:00';
    public const string NAME = 'Mario Rossi';
    public const string EMAIL = 'mario@example.com';
    public const string PASSWORD = 'una password lunga';
    public const string ORIGIN = 'https://fourstreet.studio';

    public readonly MovableClock $clock;

    public readonly InMemoryMailer $mailer;

    public readonly Database $database;

    public readonly CollectingReporter $reporter;

    public readonly Application $application;

    /**
     * @param string $origin indirizzo pubblico: https dà cookie Secure con il prefisso __Host-
     * @param array<string, mixed> $site valori di config/site.php da cambiare
     */
    public function __construct(string $origin = self::ORIGIN, array $site = [], ?Database $database = null)
    {
        $this->clock = MovableClock::at(self::NOW);
        $this->mailer = new InMemoryMailer();
        $this->database = $database ?? TestDatabase::create($this->clock);
        $this->reporter = new CollectingReporter();
        $this->application = Application::create(
            SiteFixture::config($site),
            SiteFixture::templateDirectory(),
            SiteFixture::PUBLIC_DIR,
            $this->clock,
            SiteFixture::mtimes(),
            $this->reporter,
            new Backend(
                $this->database,
                $this->mailer,
                NativePasswordHasher::bcrypt(4),
                Origin::fromUrl($origin),
                str_repeat('chiave-di-prova-', 3),
            ),
        );
    }

    public function browser(string $remoteAddress = '203.0.113.7'): TestBrowser
    {
        return new TestBrowser($this->application, $remoteAddress);
    }

    /**
     * Si registra dalla pagina di registrazione, come farebbe una persona.
     *
     * @return Response la risposta all'invio del modulo
     */
    public function register(TestBrowser $browser, string $email = self::EMAIL, string $password = self::PASSWORD, string $name = self::NAME, string $returnTo = ''): Response
    {
        $browser->get('/account/registrati/' . ($returnTo === '' ? '' : '?ritorno=' . rawurlencode($returnTo)));
        $fields = ['nome' => $name, 'email' => $email, 'password' => $password, 'privacy' => '1'];
        if ($returnTo !== '') {
            $fields['ritorno'] = $returnTo;
        }

        return $browser->post('/account/registrati/', $fields);
    }

    /**
     * Un account confermato: registrazione, link dell'email, conferma. Il browser resta dentro.
     */
    public function registerAndVerify(TestBrowser $browser, string $email = self::EMAIL, string $password = self::PASSWORD): void
    {
        $this->register($browser, $email, $password);
        $response = $browser->get(InMemoryMailer::link($this->mailer->lastTo($email), '/account/verifica/'));
        Assert::assertSame(303, $response->status, 'La conferma dell\'email non è riuscita.');
    }

    /** Accede dalla pagina di accesso. */
    public function logIn(TestBrowser $browser, string $email = self::EMAIL, string $password = self::PASSWORD): Response
    {
        $browser->get('/account/accedi/');

        return $browser->post('/account/accedi/', ['email' => $email, 'password' => $password]);
    }

    /** Il numero di righe di una tabella. */
    public function count(string $table): int
    {
        return $this->database->fetchOne('SELECT COUNT(*) AS total FROM ' . $table)?->int('total') ?? 0;
    }
}
