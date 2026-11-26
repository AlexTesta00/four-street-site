<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Application;
use FourStreet\Backend;
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

/**
 * Home, link e calendario non dipendono mai dal database: con l'area clienti configurata ma il database
 * irraggiungibile rispondono come sempre, e la connessione non si apre nemmeno.
 */
#[CoversClass(Application::class)]
#[CoversClass(Database::class)]
final class DatabaseIndependenceTest extends TestCase
{
    private const string NOW = '2026-11-25 08:00:00';

    public function testThePublicPagesNeverOpenTheDatabase(): void
    {
        // Un database che non si può aprire: la sua cartella starebbe dentro un file.
        $database = new Database(DatabaseConfig::sqlite(__FILE__ . '/non-esiste/4street.sqlite'));
        $reporter = new CollectingReporter();
        $withAccount = Application::create(
            SiteFixture::config(),
            SiteFixture::templateDirectory(),
            SiteFixture::PUBLIC_DIR,
            FrozenClock::at(self::NOW),
            SiteFixture::mtimes(),
            $reporter,
            new Backend($database, new InMemoryMailer(), NativePasswordHasher::bcrypt(4), Origin::fromUrl('https://fourstreet.studio'), str_repeat('k', 32)),
        );
        $withoutAccount = SiteFixture::application(FrozenClock::at(self::NOW));

        foreach (['/', '/?anteprima=mappa', '/links/', '/calendario.php', '/pagina-che-non-esiste'] as $uri) {
            $expected = $withoutAccount->handle(Request::create('GET', $uri));
            $actual = $withAccount->handle(Request::create('GET', $uri));

            self::assertSame($expected->status, $actual->status, $uri);
            self::assertSame($expected->headers(), $actual->headers(), $uri);
            self::assertSame($expected->body, $actual->body, $uri);
        }
        self::assertFalse($database->isConnected());
        self::assertSame([], $reporter->reported);
    }

    public function testAnAccountPageWithTheDatabaseDownIsAnErrorThatGetsReported(): void
    {
        $database = new Database(DatabaseConfig::sqlite(__FILE__ . '/non-esiste/4street.sqlite'));
        $reporter = new CollectingReporter();
        $application = Application::create(
            SiteFixture::config(),
            SiteFixture::templateDirectory(),
            SiteFixture::PUBLIC_DIR,
            FrozenClock::at(self::NOW),
            SiteFixture::mtimes(),
            $reporter,
            new Backend($database, new InMemoryMailer(), NativePasswordHasher::bcrypt(4), Origin::fromUrl('https://fourstreet.studio'), str_repeat('k', 32)),
        );

        $response = $application->handle(Request::create('GET', '/account/', [], [], ['__Host-session' => str_repeat('A', 43)]));

        self::assertSame(500, $response->status);
        self::assertCount(1, $reporter->reported);
        self::assertStringNotContainsString('non-esiste', $response->body);
    }
}
