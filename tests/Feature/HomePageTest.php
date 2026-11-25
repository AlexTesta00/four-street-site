<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature;

use FourStreet\Application;
use FourStreet\Controller\HomeController;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(HomeController::class)]
final class HomePageTest extends TestCase
{
    private const string BEFORE = '2026-11-25 08:00:00';
    private const string OPENING = '2026-12-06 15:00:00';
    private const string DAY_AFTER = '2026-12-07 00:00:00';

    private const string LIVE_SECTION = '<section id="live" class="fs-stage flex flex-1 flex-col" data-title="%s"%s>';
    private const string EMBED = 'https://www.google.com/maps?q=Via%20del%20Bando%2022%2C%20San%20Marino&amp;z=16&amp;hl=it&amp;output=embed';

    public function testBeforeTheOpeningShowsTheCountdown(): void
    {
        $response = SiteFixture::handle('GET', '/', self::BEFORE);
        $html = $response->body;

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
        self::assertStringStartsWith("<!doctype html>\n<html lang=\"it\">\n<head>", $html);
        self::assertStringContainsString('<title>4Street · Apriamo domenica 6 dicembre</title>', $html);
        self::assertStringContainsString('<meta name="description" content="4Street, palestra a San Marino. Inaugurazione domenica 6 dicembre in Via del Bando 22, San Marino.">', $html);
        self::assertStringContainsString('<meta property="og:title" content="4Street · Apriamo domenica 6 dicembre">', $html);

        self::assertStringContainsString('<section id="countdown" class="fs-stage flex flex-1 flex-col"', $html);
        self::assertStringContainsString('data-countdown', $html);
        self::assertStringContainsString('data-target="1796565600000"', $html);
        self::assertStringContainsString('data-now="1795590000000"', $html);
        self::assertSame(['giorni' => '11', 'ore' => '07', 'minuti' => '00', 'secondi' => '00'], self::countdown($html));
        self::assertStringContainsString('class="fs-cd__unit fs-anim-rise fs-step-8 fs-cd__unit--live" data-unit="secondi" aria-hidden="true"', $html);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event">06.12.2026</span>', $html);
        self::assertStringContainsString('md:leading-[1.0625]">Apriamo domenica 6 dicembre.</h1>', $html);
        self::assertStringContainsString('<a href="/calendario.php" class="fs-btn fs-btn--primary fs-anim-rise fs-step-9">', $html);
        self::assertStringContainsString('<a href="https://www.instagram.com/4street_sw" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-anim-rise fs-step-10">', $html);
        self::assertStringContainsString('text-fg-muted">Domenica 6 dicembre 2026 · ore 15:00 · Via del Bando 22, San Marino</p>', $html);

        // Lo stato "Ci siamo" è già in pagina, nascosto, con la mappa da caricare allo zero.
        self::assertStringContainsString(sprintf(self::LIVE_SECTION, '4Street · Ci siamo', ' hidden'), $html);
        self::assertStringContainsString('data-src="' . self::EMBED . '"', $html);
        self::assertStringNotContainsString(' src="https://www.google.com', $html);
    }

    public function testTheSheenDelaysOfTheCountdownCells(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        preg_match_all('/style="--sheen-delay: (\d+)ms"/', $html, $delays);

        self::assertSame(['2400', '2480', '2560', '2640', '2720', '2800', '2880', '2960'], $delays[1]);
    }

    public function testSharedLayoutAndVersionedAssets(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertStringContainsString('<link rel="stylesheet" href="/assets/css/app.css?v=' . SiteFixture::CSS_MTIME . '">', $html);
        self::assertStringContainsString('<script src="/assets/js/app.js?v=' . SiteFixture::JS_MTIME . '" defer></script>', $html);
        self::assertStringContainsString('<img src="/assets/img/logo.jpg?v=' . SiteFixture::LOGO_MTIME . '" alt="4Street"', $html);
        self::assertStringContainsString('<meta property="og:image" content="https://fourstreet.studio/assets/img/logo.jpg">', $html);
        self::assertStringContainsString('<link rel="icon" href="/assets/img/logo.jpg">', $html);
        self::assertStringContainsString('<a href="/links/" class="fs-btn fs-btn--ghost fs-btn--sm">', $html);
        self::assertStringContainsString('San Marino · 06.12.2026</span>', $html);
        self::assertStringEndsWith("</script>\n</body>\n</html>\n", $html);
    }

    public function testOneSecondBeforeTheOpening(): void
    {
        $html = SiteFixture::handle('GET', '/', '2026-12-06 14:59:59')->body;

        self::assertSame(['giorni' => '00', 'ore' => '00', 'minuti' => '00', 'secondi' => '01'], self::countdown($html));
        self::assertStringContainsString(sprintf(self::LIVE_SECTION, '4Street · Ci siamo', ' hidden'), $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function openingDay(): iterable
    {
        yield 'all\'istante esatto' => [self::OPENING];
        yield 'la sera' => ['2026-12-06 21:00:00'];
        yield 'all\'ultimo secondo del giorno' => ['2026-12-06 23:59:59'];
    }

    #[DataProvider('openingDay')]
    public function testDuringTheOpeningDayShowsTheMap(string $now): void
    {
        $html = SiteFixture::handle('GET', '/', $now)->body;

        self::assertStringNotContainsString('id="countdown"', $html);
        self::assertStringContainsString('<title>4Street · Ci siamo</title>', $html);
        self::assertStringContainsString('<meta name="description" content="4Street, palestra a San Marino. Ti aspettiamo in Via del Bando 22, San Marino.">', $html);
        self::assertStringContainsString(sprintf(self::LIVE_SECTION, '4Street · Ci siamo', ''), $html);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event fs-anim-rise fs-step-1 self-start"><span class="fs-tag__dot fs-anim-breathe"></span>In corso</span>', $html);
        self::assertStringContainsString('tracking-[-0.02em]">Ci siamo.</h1>', $html);
        self::assertStringContainsString('text-fg-muted">L\'inaugurazione è iniziata. Ti aspettiamo in Via del Bando 22, San Marino.</p>', $html);
        self::assertStringContainsString(' src="' . self::EMBED . '"', $html);
        self::assertStringNotContainsString('data-src=', $html);
        self::assertStringContainsString('<a href="https://www.google.com/maps/dir/?api=1&amp;destination=Via%20del%20Bando%2022%2C%20San%20Marino" target="_blank" rel="noopener" class="fs-btn fs-btn--primary fs-anim-rise fs-step-7">', $html);
        self::assertStringContainsString('<a href="https://maps.apple.com/?daddr=Via%20del%20Bando%2022%2C%20San%20Marino" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-anim-rise fs-step-8">Apple Mappe</a>', $html);
        self::assertStringContainsString('data-copy="Via del Bando 22, San Marino"', $html);
        self::assertStringContainsString('<span class="fs-btn__label" data-copy-label>Copia indirizzo</span>', $html);
        self::assertSame(['Data' => 'Domenica 6 dicembre 2026', 'Orario' => '15:00', 'Luogo' => 'Via del Bando 22, San Marino'], self::details($html));
        self::assertStringContainsString('<script src="/assets/js/app.js', $html);
    }

    public function testFromTheDayAfterTheGymIsOpen(): void
    {
        $html = SiteFixture::handle('GET', '/', self::DAY_AFTER)->body;

        self::assertStringContainsString('<title>4Street · Siamo aperti</title>', $html);
        self::assertStringContainsString(sprintf(self::LIVE_SECTION, '4Street · Siamo aperti', ''), $html);
        self::assertStringContainsString('<span class="fs-tag__dot fs-anim-breathe"></span>Aperti</span>', $html);
        self::assertStringContainsString('tracking-[-0.02em]">Siamo aperti.</h1>', $html);
        self::assertStringContainsString('text-fg-muted">Ti aspettiamo in Via del Bando 22, San Marino.</p>', $html);
        self::assertStringNotContainsString('L\'inaugurazione è iniziata.', $html);
        self::assertStringNotContainsString('In corso', $html);
    }

    public function testMapPreviewBeforeTheOpening(): void
    {
        $html = SiteFixture::handle('GET', '/?anteprima=mappa', self::BEFORE)->body;

        self::assertStringNotContainsString('id="countdown"', $html);
        self::assertStringContainsString('<title>4Street · Ci siamo</title>', $html);
        self::assertStringContainsString(sprintf(self::LIVE_SECTION, '4Street · Ci siamo', ''), $html);
        self::assertStringContainsString(' src="' . self::EMBED . '"', $html);
    }

    public function testZeroPreviewEndsTheCountdownInTwelveSeconds(): void
    {
        $html = SiteFixture::handle('GET', '/?anteprima=zero', self::BEFORE)->body;

        self::assertStringContainsString('data-target="1795590012000"', $html);
        self::assertStringContainsString('data-now="1795590000000"', $html);
        self::assertSame(['giorni' => '00', 'ore' => '00', 'minuti' => '00', 'secondi' => '12'], self::countdown($html));
        self::assertStringContainsString('<title>4Street · Apriamo domenica 6 dicembre</title>', $html);
        self::assertStringContainsString(sprintf(self::LIVE_SECTION, '4Street · Ci siamo', ' hidden'), $html);
    }

    public function testZeroPreviewAfterTheEventLeadsToTheOpenState(): void
    {
        $html = SiteFixture::handle('GET', '/?anteprima=zero', '2026-12-10 18:00:00')->body;

        self::assertSame(['giorni' => '00', 'ore' => '00', 'minuti' => '00', 'secondi' => '12'], self::countdown($html));
        self::assertStringContainsString(sprintf(self::LIVE_SECTION, '4Street · Siamo aperti', ' hidden'), $html);
        self::assertStringContainsString('tracking-[-0.02em]">Siamo aperti.</h1>', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredPreviews(): iterable
    {
        yield 'sconosciuta' => ['/?anteprima=altro'];
        yield 'vuota' => ['/?anteprima='];
        yield 'array' => ['/?anteprima[]=mappa'];
    }

    #[DataProvider('ignoredPreviews')]
    public function testUnknownPreviewsAreIgnored(string $uri): void
    {
        self::assertSame(
            SiteFixture::handle('GET', '/', self::BEFORE)->body,
            SiteFixture::handle('GET', $uri, self::BEFORE)->body,
        );
    }

    public function testWithoutAConfirmedTimeTheHourIsNotShown(): void
    {
        $before = SiteFixture::handle('GET', '/', self::BEFORE, ['opening_time_confirmed' => false])->body;
        $live = SiteFixture::handle('GET', '/', self::OPENING, ['opening_time_confirmed' => false])->body;

        self::assertStringContainsString('text-fg-muted">Domenica 6 dicembre 2026 · Via del Bando 22, San Marino</p>', $before);
        self::assertStringNotContainsString('15:00', $before);
        self::assertSame(['Data' => 'Domenica 6 dicembre 2026', 'Luogo' => 'Via del Bando 22, San Marino'], self::details($live));
    }

    public function testOptionalLinksAndCustomMap(): void
    {
        $overrides = [
            'links' => ['instagram' => '', 'whatsapp' => ''],
            'maps_url' => 'https://maps.app.goo.gl/AbC123?g=1&h=2',
            'site_url' => '',
        ];
        $before = SiteFixture::handle('GET', '/', self::BEFORE, $overrides)->body;
        $live = SiteFixture::handle('GET', '/', self::OPENING, $overrides)->body;

        self::assertStringNotContainsString('Seguici su Instagram', $before);
        self::assertStringNotContainsString('og:image', $before);
        self::assertStringContainsString('<a href="https://maps.app.goo.gl/AbC123?g=1&amp;h=2" target="_blank" rel="noopener" class="fs-btn fs-btn--primary', $live);
    }

    public function testTheAddressIsEscapedEverywhere(): void
    {
        $html = SiteFixture::handle('GET', '/', self::OPENING, ['address' => 'Via "Nuova" 1 & <b>Borgo</b>'])->body;

        self::assertStringNotContainsString('<b>Borgo</b>', $html);
        self::assertStringContainsString('data-copy="Via &quot;Nuova&quot; 1 &amp; &lt;b&gt;Borgo&lt;/b&gt;"', $html);
        self::assertStringContainsString('title="Mappa: Via &quot;Nuova&quot; 1 &amp; &lt;b&gt;Borgo&lt;/b&gt;"', $html);
        self::assertStringContainsString('q=Via%20%22Nuova%22%201%20%26%20%3Cb%3EBorgo%3C%2Fb%3E', $html);
    }

    public function testHeadAnswersWithTheHeadersOnly(): void
    {
        $get = SiteFixture::handle('GET', '/', self::BEFORE);
        $head = SiteFixture::handle('HEAD', '/', self::BEFORE);

        self::assertSame(200, $head->status);
        self::assertSame('', $head->body);
        self::assertSame($get->headers(), $head->headers());
    }

    public function testSecurityHeaders(): void
    {
        $response = SiteFixture::handle('GET', '/', self::BEFORE);

        self::assertStringContainsString('frame-src https://www.google.com', (string) $response->header('Content-Security-Policy'));
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
        self::assertFalse($response->hasHeader('Cache-Control'));
    }

    /**
     * Le due cifre di ogni casella del countdown.
     *
     * @return array<string, string>
     */
    private static function countdown(string $html): array
    {
        preg_match_all('#data-unit="(\w+)".*?<div class="fs-cd__digits">(.*?)</div>#s', $html, $units, PREG_SET_ORDER);

        $digits = [];
        foreach ($units as $unit) {
            preg_match_all('#<span class="fs-cd__new">(\d)</span>#', $unit[2], $cells);
            $digits[$unit[1]] = implode('', $cells[1]);
        }

        return $digits;
    }

    /**
     * Le righe dell'elenco Data, Orario, Luogo.
     *
     * @return array<string, string>
     */
    private static function details(string $html): array
    {
        preg_match_all('#<dt class="fs-label">([^<]+)</dt>\s*<dd>([^<]+)</dd>#', $html, $rows);

        return array_combine($rows[1], $rows[2]);
    }
}
