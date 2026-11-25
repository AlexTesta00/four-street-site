<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature;

use FourStreet\Application;
use FourStreet\Controller\NotFoundController;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(NotFoundController::class)]
final class NotFoundPageTest extends TestCase
{
    private const string NOW = '2026-11-25 08:00:00';

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownAddresses(): iterable
    {
        yield 'pagina inesistente' => ['/prenotazioni'];
        yield 'cartella degli asset' => ['/assets/'];
        yield 'file statico mancante' => ['/assets/css/vecchio.css'];
        yield 'calendario con la barra' => ['/calendario.php/'];
        yield 'vecchia cartella interna' => ['/_inc/config.php'];
    }

    #[DataProvider('unknownAddresses')]
    public function testUnknownAddressesShowTheStyledNotFoundPage(string $uri): void
    {
        $response = SiteFixture::handle('GET', $uri, self::NOW);
        $html = $response->body;

        self::assertSame(404, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
        self::assertStringStartsWith("<!doctype html>\n<html lang=\"it\">", $html);
        self::assertStringContainsString('<title>4Street · Pagina non trovata</title>', $html);
        self::assertStringContainsString('<link rel="stylesheet" href="/assets/css/app.css?v=' . SiteFixture::CSS_MTIME . '">', $html);
        self::assertStringContainsString('<header class="fs-bar fs-gutter fs-anim-fade">', $html);
        self::assertStringContainsString('San Marino · 06.12.2026</span>', $html);
        self::assertStringContainsString('>Pagina non trovata.</h1>', $html);
        self::assertStringContainsString('<a href="/" class="fs-btn fs-btn--primary fs-anim-rise fs-step-4">Torna alla home</a>', $html);
        self::assertStringContainsString('<svg class="fs-lane fs-anim-wipe-right fs-step-6"', $html);
        self::assertStringNotContainsString('id="countdown"', $html);
        self::assertTrue($response->hasHeader('Content-Security-Policy'));
    }

    public function testHeadOnAnUnknownAddress(): void
    {
        $response = SiteFixture::handle('HEAD', '/prenotazioni', self::NOW);

        self::assertSame(404, $response->status);
        self::assertSame('', $response->body);
    }

    public function testOtherMethodsOnKnownPagesAreNotAllowed(): void
    {
        $response = SiteFixture::handle('POST', '/', self::NOW);

        self::assertSame(405, $response->status);
        self::assertSame('GET, HEAD', $response->header('Allow'));
        self::assertSame('text/plain; charset=utf-8', $response->header('Content-Type'));
    }
}
