<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Application;
use FourStreet\Backend;
use FourStreet\Booking\Landing\FailSafePreviewSource;
use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Http\Origin;
use FourStreet\Http\Request;
use FourStreet\Security\Password\NativePasswordHasher;
use FourStreet\Tests\Support\CollectingReporter;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\InMemoryMailer;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Home, link e calendario non dipendono mai dal database. Link e calendario non lo aprono mai;
 * la home, con le prenotazioni attive, lo legge una volta per i posti: se non risponde, registra
 * l'errore e mostra gli orari senza numeri. Mai un 500.
 */
#[CoversClass(Application::class)]
#[CoversClass(Database::class)]
#[CoversClass(FailSafePreviewSource::class)]
final class DatabaseIndependenceTest extends TestCase
{
    private const string NOW = '2026-11-25 08:00:00';

    public function testWithTheDatabaseDownThePublicPagesStillAnswerAndTheHomeDropsTheNumbers(): void
    {
        $reporter = new CollectingReporter();
        $application = self::application(self::brokenDatabase(), $reporter, online: true);

        $home = $application->handle(Request::create('GET', '/'));
        self::assertSame(200, $home->status);
        self::assertSame('no-cache', $home->header('Cache-Control'));
        // Gli orari del primo giorno prenotabile, senza posti né numeri, e il link alla pagina delle fasce.
        self::assertStringContainsString('>Le fasce di lunedì 7 dicembre. I posti liberi li vedi nella pagina delle prenotazioni.</p>', $home->body);
        self::assertStringContainsString('<ul class="fs-slots" aria-label="Fasce di lunedì 7 dicembre">', $home->body);
        self::assertSame(6, substr_count($home->body, '<li class="fs-slot__row fs-slot__row--plain">'));
        self::assertStringNotContainsString('fs-slot__input', $home->body);
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-07" class="fs-btn fs-btn--primary fs-btn--block" data-primary-cta>', $home->body);
        self::assertStringNotContainsString('[Fasce illustrative]', $home->body);

        // Il resto del sito non lo nota nemmeno.
        foreach (['/?anteprima=mappa' => 200, '/links/' => 200, '/calendario.php' => 200, '/pagina-che-non-esiste' => 404] as $uri => $status) {
            self::assertSame($status, $application->handle(Request::create('GET', $uri))->status, $uri);
        }

        // Ogni home ha registrato l'errore del database nel log, e nessun'altra pagina ci ha provato.
        self::assertCount(2, $reporter->reported);
        self::assertContainsOnlyInstancesOf(RuntimeException::class, $reporter->reported);
    }

    public function testLinksAndCalendarNeverOpenTheDatabase(): void
    {
        $database = self::brokenDatabase();
        $reporter = new CollectingReporter();
        $application = self::application($database, $reporter, online: true);

        foreach (['/links/', '/calendario.php', '/pagina-che-non-esiste'] as $uri) {
            $application->handle(Request::create('GET', $uri));
        }

        self::assertFalse($database->isConnected());
        self::assertSame([], $reporter->reported);
    }

    public function testWithBookingsSwitchedOffNoPublicPageOpensTheDatabase(): void
    {
        $database = self::brokenDatabase();
        $reporter = new CollectingReporter();
        $withAccount = self::application($database, $reporter, online: false);
        $withoutAccount = SiteFixture::application(FrozenClock::at(self::NOW));

        foreach (['/', '/?anteprima=mappa', '/links/', '/calendario.php', '/pagina-che-non-esiste'] as $uri) {
            $expected = $withoutAccount->handle(Request::create('GET', $uri));
            $actual = $withAccount->handle(Request::create('GET', $uri));

            self::assertSame($expected->status, $actual->status, $uri);
            self::assertSame($expected->headers(), $actual->headers(), $uri);
        }
        self::assertFalse($database->isConnected());
        self::assertSame([], $reporter->reported);
    }

    public function testAnAccountPageWithTheDatabaseDownIsAnErrorThatGetsReported(): void
    {
        $reporter = new CollectingReporter();
        $application = self::application(self::brokenDatabase(), $reporter, online: true);

        $response = $application->handle(Request::create('GET', '/account/', [], [], ['__Host-session' => str_repeat('A', 43)]));

        self::assertSame(500, $response->status);
        self::assertCount(1, $reporter->reported);
        self::assertStringNotContainsString('non-esiste', $response->body);
    }

    /** Un database che non si può aprire: la sua cartella starebbe dentro un file. */
    private static function brokenDatabase(): Database
    {
        return new Database(DatabaseConfig::sqlite(__FILE__ . '/non-esiste/4street.sqlite'));
    }

    private static function application(Database $database, CollectingReporter $reporter, bool $online): Application
    {
        return Application::create(
            SiteFixture::config(['booking' => SiteFixture::booking(['online' => $online])]),
            SiteFixture::templateDirectory(),
            SiteFixture::PUBLIC_DIR,
            FrozenClock::at(self::NOW),
            SiteFixture::mtimes(),
            $reporter,
            new Backend($database, new InMemoryMailer(), NativePasswordHasher::bcrypt(4), Origin::fromUrl('https://fourstreet.studio'), str_repeat('k', 32)),
        );
    }
}
