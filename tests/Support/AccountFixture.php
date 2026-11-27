<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\User;
use FourStreet\Application;
use FourStreet\Backend;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\SlotTime;
use FourStreet\Config\SiteConfig;
use FourStreet\Database\Database;
use FourStreet\Database\Row;
use FourStreet\Http\Origin;
use FourStreet\Http\Response;
use FourStreet\Security\Password\NativePasswordHasher;
use PHPUnit\Framework\Assert;

/**
 * L'applicazione vera con l'area clienti e le prenotazioni attive: database dei test (SQLite in memoria),
 * posta in memoria, bcrypt con il costo minimo e un orologio che il test può spostare.
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

    public readonly SiteConfig $site;

    public readonly Application $application;

    /**
     * @param string $origin indirizzo pubblico: https dà cookie Secure con il prefisso __Host-
     * @param array<string, mixed> $site valori di config/site.php da cambiare
     */
    public function __construct(string $origin = self::ORIGIN, array $site = [], ?Database $database = null, string $now = self::NOW)
    {
        $this->clock = MovableClock::at($now);
        $this->mailer = new InMemoryMailer();
        $this->database = $database ?? TestDatabase::create($this->clock);
        $this->reporter = new CollectingReporter();
        $this->site = SiteFixture::config($site);
        $this->application = Application::create(
            $this->site,
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

    /**
     * Un cliente con l'email confermata, scritto direttamente nel database (senza passare dalle pagine).
     * La password è quella dei test: si entra con logIn($browser, $email).
     */
    public function customer(string $email, string $name = 'Cliente Prova'): User
    {
        $users = new PdoUserRepository($this->database);
        $fullName = FullName::tryFrom($name);
        $address = EmailAddress::tryFrom($email);
        Assert::assertNotNull($fullName);
        Assert::assertNotNull($address);
        $hash = NativePasswordHasher::bcrypt(4)->hash(self::PASSWORD);
        $user = $users->create(new NewUser($fullName, $address, $hash, $this->clock->now(), str_repeat('0', 64)));
        $users->markEmailVerified($user->id, $this->clock->now());
        $verified = $users->findById($user->id);
        Assert::assertNotNull($verified);

        return $verified;
    }

    /** L'account confermato con quell'email, letto dal database. */
    public function user(string $email = self::EMAIL): User
    {
        $address = EmailAddress::tryFrom($email);
        Assert::assertNotNull($address);
        $user = (new PdoUserRepository($this->database))->findByEmail($address);
        Assert::assertNotNull($user, sprintf('Nessun account %s.', $email));

        return $user;
    }

    /**
     * Una prenotazione scritta direttamente nel database, come se il cliente l'avesse fatta dal sito
     * (stesso repository, senza limiti né email): per riempire una fascia o preparare uno storico.
     */
    public function book(User $customer, string $date, string $time): Booking
    {
        $day = LocalDate::tryFrom($date);
        $start = SlotTime::tryFrom($time);
        Assert::assertNotNull($day);
        Assert::assertNotNull($start);
        $slot = BookingCalendar::forSite($this->site)->slot($day, $start);
        Assert::assertNotNull($slot, sprintf('Nessuna fascia il %s alle %s.', $date, $time));
        $result = (new PdoBookingRepository($this->database))->place($customer->id, $slot, $this->site->booking->capacity, null, $this->clock->now());
        Assert::assertSame(BookingStatus::Booked, $result->status);

        return $result->booking();
    }

    /** I posti presi di una fascia, letti dal database. */
    public function seats(string $date, string $time): string
    {
        $rows = $this->database->fetchAll(
            'SELECT seat FROM bookings WHERE slot_date = :slot_date AND slot_time = :slot_time AND seat IS NOT NULL ORDER BY seat',
            ['slot_date' => $date, 'slot_time' => $time],
        );

        return implode(',', array_map(static fn(Row $row): int => $row->int('seat'), $rows));
    }
}
