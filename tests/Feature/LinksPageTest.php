<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature;

use FourStreet\Application;
use FourStreet\Controller\LinksController;
use FourStreet\Controller\RedirectController;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(LinksController::class)]
#[CoversClass(RedirectController::class)]
final class LinksPageTest extends TestCase
{
    private const string BEFORE = '2026-11-25 08:00:00';

    public function testTheLinksPage(): void
    {
        $response = SiteFixture::handle('GET', '/links/', self::BEFORE);
        $html = $response->body;

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
        self::assertStringContainsString('<title>4Street · Link</title>', $html);
        self::assertStringContainsString('<meta name="description" content="Tutti i link di 4Street, palestra a San Marino.">', $html);
        self::assertStringContainsString('<h1 class="sr-only">4Street</h1>', $html);
        self::assertStringContainsString('<nav aria-label="Link di 4Street"', $html);
        self::assertStringContainsString('<img src="/assets/img/logo.jpg?v=' . SiteFixture::LOGO_MTIME . '"', $html);
        self::assertStringContainsString('class="fs-anim-image object-[50%_48%] lg:object-center"', $html);
        // La pagina dei link non usa lo script e non ha la barra in alto.
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('fs-bar', $html);
        self::assertStringContainsString('<svg class="fs-lane fs-anim-wipe-right fs-step-9"', $html);
    }

    public function testCardsInOrderWithTheirEntranceSteps(): void
    {
        $html = SiteFixture::handle('GET', '/links/', self::BEFORE)->body;

        self::assertSame([
            4 => 'Inaugurazione',
            5 => 'Instagram',
            6 => 'WhatsApp',
            7 => 'Come raggiungerci',
            8 => 'Prenotazioni',
            9 => 'Shop',
        ], self::cards($html));
        self::assertStringContainsString('<a href="/" class="fs-link fs-link--featured">', $html);
        self::assertStringContainsString('<span class="fs-link__meta">Domenica 6 dicembre 2026</span>', $html);
        self::assertStringContainsString('<a href="https://www.instagram.com/4street_sw" target="_blank" rel="noopener" class="fs-link">', $html);
        self::assertStringContainsString('<a href="https://wa.me/393662546495" target="_blank" rel="noopener" class="fs-link">', $html);
        self::assertStringContainsString('<a href="https://www.google.com/maps/dir/?api=1&amp;destination=Via%20del%20Bando%2022%2C%20San%20Marino" target="_blank" rel="noopener" class="fs-link">', $html);
        self::assertSame(2, substr_count($html, '<div class="fs-link fs-link--soon" aria-disabled="true">'));
        self::assertSame(2, substr_count($html, '<span class="fs-tag flex-none">Presto</span>'));
        self::assertStringContainsString('<span class="fs-link__meta">Vendita online</span>', $html);
        self::assertSame(3, substr_count($html, 'class="fs-link__arrow"'));
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function timeLeft(): iterable
    {
        yield 'undici giorni' => [self::BEFORE, '', 11, 'giorni'];
        yield 'un giorno e un\'ora' => ['2026-12-05 14:00:00', '', 1, 'giorno'];
        yield 'l\'ultimo giorno, in ore' => ['2026-12-06 12:00:00', '', 3, 'ore'];
        yield 'pochi minuti: un\'ora' => ['2026-12-06 14:59:00', '', 1, 'ora'];
        yield 'anteprima zero' => [self::BEFORE, '?anteprima=zero', 1, 'ora'];
    }

    #[DataProvider('timeLeft')]
    public function testBeforeTheOpeningTheFeaturedCardSaysHowLongIsLeft(string $now, string $query, int $value, string $unit): void
    {
        $html = SiteFixture::handle('GET', '/links/' . $query, $now)->body;

        self::assertStringContainsString(sprintf('[font-variant-numeric:lining-nums_tabular-nums]">%02d</span>', $value), $html);
        self::assertStringContainsString('<span aria-hidden="true" class="fs-micro text-accent">' . $unit . '</span>', $html);
        self::assertStringContainsString('<span class="sr-only">Mancano ' . $value . ' ' . $unit . '</span>', $html);
        self::assertStringNotContainsString('fs-tag--event', $html);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function liveStates(): iterable
    {
        yield 'il giorno dell\'inaugurazione' => ['2026-12-06 15:00:00', '', 'In corso'];
        yield 'anteprima mappa' => [self::BEFORE, '?anteprima=mappa', 'In corso'];
        yield 'dal giorno dopo' => ['2026-12-07 00:00:00', '', 'Aperti'];
    }

    #[DataProvider('liveStates')]
    public function testOnceLiveTheFeaturedCardShowsTheState(string $now, string $query, string $state): void
    {
        $html = SiteFixture::handle('GET', '/links/' . $query, $now)->body;

        self::assertStringContainsString('<span class="fs-tag fs-tag--event flex-none"><span class="fs-tag__dot fs-anim-breathe"></span>' . $state . '</span>', $html);
        self::assertStringNotContainsString('Mancano', $html);
    }

    public function testMissingLinksShiftTheFollowingCards(): void
    {
        $html = SiteFixture::handle('GET', '/links/', self::BEFORE, [
            'links' => ['instagram' => 'https://www.instagram.com/4street_sw', 'whatsapp' => ''],
            'maps_url' => 'https://maps.app.goo.gl/AbC123',
        ])->body;

        self::assertSame([4 => 'Inaugurazione', 5 => 'Instagram', 6 => 'Come raggiungerci', 7 => 'Prenotazioni', 8 => 'Shop'], self::cards($html));
        self::assertStringContainsString('<a href="https://maps.app.goo.gl/AbC123" target="_blank" rel="noopener" class="fs-link">', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function redirects(): iterable
    {
        yield 'senza barra finale' => ['/links', '/links/'];
        yield 'con un\'anteprima' => ['/links?anteprima=mappa', '/links/?anteprima=mappa'];
        yield 'vecchio file PHP' => ['/links/index.php', '/links/'];
        yield 'vecchio file PHP della home' => ['/index.php?anteprima=zero', '/?anteprima=zero'];
    }

    #[DataProvider('redirects')]
    public function testOldAndNonCanonicalAddressesRedirect(string $uri, string $location): void
    {
        $response = SiteFixture::handle('GET', $uri, self::BEFORE);

        self::assertSame(301, $response->status);
        self::assertSame($location, $response->header('Location'));
        self::assertSame('', $response->body);
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    /**
     * Titoli delle card, indicizzati per passo di entrata (fs-step-N).
     *
     * @return array<int, string>
     */
    private static function cards(string $html): array
    {
        preg_match_all('#<li class="fs-anim-rise fs-step-(\d+)">.*?<span class="fs-title-caps">([^<]+)</span>#s', $html, $cards);

        return array_combine(array_map(intval(...), $cards[1]), $cards[2]);
    }
}
