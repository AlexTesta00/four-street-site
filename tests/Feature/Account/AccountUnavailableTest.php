<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Account\AccountModule;
use FourStreet\Account\Http\AccountUnavailableController;
use FourStreet\Application;
use FourStreet\Http\Request;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Il pacchetto di deploy senza config/local.php: l'area clienti risponde 503 con una pagina cortese,
 * il resto del sito come sempre.
 */
#[CoversClass(Application::class)]
#[CoversClass(AccountModule::class)]
#[CoversClass(AccountUnavailableController::class)]
final class AccountUnavailableTest extends TestCase
{
    private const string NOW = '2026-11-25 08:00:00';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accountPages(): iterable
    {
        foreach (AccountModule::ENDPOINTS as $path => $methods) {
            foreach ($methods as $method) {
                yield $method . ' ' . $path => [$method, $path];
            }
        }
    }

    #[DataProvider('accountPages')]
    public function testEveryAccountPageIsPolitelyUnavailable(string $method, string $path): void
    {
        $response = SiteFixture::handle($method, $path, self::NOW);

        self::assertSame(503, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame('noindex', $response->header('X-Robots-Tag'));
        self::assertStringContainsString('<title>4Street · Area clienti</title>', $response->body);
        self::assertStringContainsString('Arriva presto.</h1>', $response->body);
        self::assertStringContainsString('<a href="https://wa.me/393662546495" target="_blank" rel="noopener" class="fs-btn fs-btn--primary fs-btn--block">', $response->body);
        self::assertStringContainsString('<a href="/" class="fs-btn fs-btn--secondary fs-btn--block">Torna alla home</a>', $response->body);
    }

    public function testTheSameAddressesAndMethodsAsTheRealAccountPages(): void
    {
        self::assertSame(405, SiteFixture::handle('DELETE', '/account/esci/', self::NOW)->status);
        self::assertSame(405, SiteFixture::handle('POST', '/account/', self::NOW)->status);
        self::assertSame(404, SiteFixture::handle('GET', '/account/altro/', self::NOW)->status);
        self::assertSame(301, SiteFixture::handle('GET', '/account', self::NOW)->status);
    }

    public function testTheRestOfTheSiteDoesNotChange(): void
    {
        $application = SiteFixture::application(FrozenClock::at(self::NOW));

        foreach (['/' => 200, '/links/' => 200, '/calendario.php' => 200, '/pagina-che-non-esiste' => 404] as $path => $status) {
            $response = $application->handle(Request::create('GET', $path));
            self::assertSame($status, $response->status, $path);
            self::assertFalse($response->hasHeader('Set-Cookie'), $path);
        }
    }
}
