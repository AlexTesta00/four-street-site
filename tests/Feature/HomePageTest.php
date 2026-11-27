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

    private const string DIRECTIONS = 'https://www.google.com/maps/dir/?api=1&amp;destination=Via%20del%20Bando%2022%2C%20San%20Marino';
    private const string WHATSAPP = 'https://wa.me/393662546495';

    private const array SECTIONS = [
        '<section aria-labelledby="titolo">',
        '<section id="regola" aria-labelledby="regola-titolo" class="scroll-mt-4">',
        '<section id="fasce" aria-labelledby="fasce-titolo" class="scroll-mt-4">',
        '<section id="spazio" aria-labelledby="spazio-titolo" class="scroll-mt-4">',
        '<section id="dove" aria-labelledby="dove-titolo" class="scroll-mt-4">',
        '<section id="domande" aria-labelledby="domande-titolo" class="scroll-mt-4">',
        '<section aria-labelledby="invito-titolo">',
    ];

    private const array PLACEHOLDERS = [
        '<span class="fs-ph">[Discipline e attrezzatura da confermare]</span>',
        '[Foto: la sala, bianco e nero]',
        '[Foto: l&#039;ingresso, bianco e nero]',
        '[Foto: un dettaglio della sala, bianco e nero]',
        '<span class="fs-ph">[Orari da confermare]</span>',
        '<span class="fs-ph">[Prezzi da confermare]</span>',
        '<span class="fs-tag fs-tag--placeholder">[Informativa privacy]</span>',
        '<span class="fs-tag fs-tag--placeholder">[Ragione sociale e COE da confermare]</span>',
    ];

    public function testBeforeTheOpening(): void
    {
        $response = SiteFixture::handle('GET', '/', self::BEFORE);
        $html = $response->body;

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
        self::assertStringStartsWith("<!doctype html>\n<html lang=\"it\">\n<head>", $html);
        self::assertStringContainsString('<title>4Street · Apriamo domenica 6 dicembre</title>', $html);
        self::assertStringContainsString('<meta name="description" content="4Street, palestra a San Marino. Cinque persone per fascia oraria, non una di più. Inaugurazione domenica 6 dicembre in Via del Bando 22, San Marino.">', $html);
        self::assertStringContainsString('<meta property="og:title" content="4Street · Apriamo domenica 6 dicembre">', $html);

        // Il blocco dell'inaugurazione: tag, data, countdown e calendario. Allo zero lo script lo fa uscire.
        self::assertStringContainsString('<div class="fs-hero__state fs-hero__state--event fs-stage" data-state="pre"', $html);
        self::assertStringContainsString('data-target="1796565600000"', $html);
        self::assertStringContainsString('data-now="1795590000000"', $html);
        self::assertStringContainsString('data-open-title="4Street · Siamo aperti"', $html);
        self::assertSame(['giorni' => '11', 'ore' => '07', 'minuti' => '00', 'secondi' => '00'], self::countdown($html));
        self::assertStringContainsString('<div class="fs-cd" role="timer" aria-label="Conto alla rovescia per l\'inaugurazione">', $html);
        self::assertStringContainsString('class="fs-cd__unit fs-anim-rise fs-step-12 fs-cd__unit--live" data-unit="secondi" aria-hidden="true"', $html);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event fs-anim-rise fs-step-8">06.12.2026</span>', $html);
        self::assertStringContainsString('lg:text-lead">Inaugurazione domenica 6 dicembre 2026, ore 15:00.</p>', $html);
        self::assertStringContainsString('<a href="/calendario.php" class="fs-btn fs-btn--ghost fs-anim-rise fs-step-12 -ml-2">', $html);

        // Lo stato "aperti" è già in pagina, nascosto; le frasi hanno le due versioni.
        self::assertStringContainsString('<div class="fs-hero__state" data-state="open" hidden>', $html);
        self::assertStringContainsString('San Marino · <span data-state="open" hidden>Aperti</span><span data-state="pre">06.12.2026</span>', $html);
        self::assertStringContainsString('Via del Bando 22, San Marino. <span data-state="open" hidden>Siamo aperti.</span><span data-state="pre">Apriamo domenica 6 dicembre 2026.</span></p>', $html);
        self::assertStringContainsString('<dd>Domenica 6 dicembre 2026<span data-state="pre">, ore 15:00</span></dd>', $html);
        self::assertStringContainsString('<span data-state="open" hidden>Siamo aperti. Abbiamo inaugurato domenica 6 dicembre 2026.</span><span data-state="pre">Inauguriamo domenica 6 dicembre 2026, ore 15:00.</span>', $html);
        self::assertStringContainsString('<span data-state="open" hidden>Siamo aperti.</span><span data-state="pre">Inaugurazione domenica 6 dicembre 2026, ore 15:00.</span></p>', $html);
        self::assertSame(5, substr_count($html, 'data-state="open" hidden'));
    }

    public function testEverySectionInOrderWithItsKeyCopy(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        $positions = array_map(static fn(string $section): int|false => strpos($html, $section), self::SECTIONS);
        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);

        foreach ([
            '<p class="fs-hero__eyebrow fs-label fs-anim-rise fs-step-1 text-fg-subtle">Palestra · San Marino</p>',
            '>Cinque è il limite. Il resto è spazio.</h2>',
            '>Ogni fascia oraria ha cinque posti. Per te vuol dire questo.</p>',
            '<p class="text-lead text-fg">In sala siete al massimo in cinque.</p>',
            '<p class="text-lead text-fg">Quando la fascia è piena, non entra nessun altro.</p>',
            '<p class="text-lead text-fg">Il numero non cambia: vale per ogni fascia.</p>',
            '>Così prenoterai il tuo posto.</h2>',
            '>Prenotare dal sito sarà possibile a breve. Le fasce vere saranno pubblicate qui.</p>',
            '<p class="fs-title-caps text-fg">Scegli la fascia</p>',
            '<p class="fs-title-caps text-fg">Prenoti il posto</p>',
            '<p class="text-body-sm text-fg-muted">Dal sito, presto. Oggi su WhatsApp.</p>',
            '<p class="fs-title-caps text-fg">Ti alleni</p>',
            '<p class="text-body-sm text-fg-muted">Vieni in Via del Bando 22, San Marino all&#039;ora scelta.</p>',
            '>Una sala per cinque.</h2>',
            '<figcaption class="text-body-sm text-fg-subtle">La sala.</figcaption>',
            '<figcaption class="text-body-sm text-fg-subtle">L&#039;ingresso, in Via del Bando 22, San Marino.</figcaption>',
            '<figcaption class="text-body-sm text-fg-subtle">Un dettaglio.</figcaption>',
            '<h2 id="dove-titolo" class="font-display text-display-md text-balance text-fg">Via del Bando 22, San Marino.</h2>',
            '>Ti aspettiamo qui. Apri il percorso e vieni a vedere la sala.</p>',
            '>Prima di venire.</h2>',
            '>I posti sono cinque. Vieni a vederli.</h2>',
        ] as $copy) {
            self::assertStringContainsString($copy, $html);
        }
    }

    public function testOneHeadingOneAndAccessibleLandmarks(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('<h1 id="titolo" class="fs-hero__title fs-anim-rise fs-step-3 max-w-content font-display text-display-md text-balance text-fg"><span class="sr-only">5 </span>persone per fascia oraria. Non una di più.</h1>', $html);
        self::assertSame(6, substr_count($html, '<h2 id="'));
        self::assertStringContainsString("<body>\n\n<a href=\"#contenuto\" class=\"fs-skip fs-label\">Vai al contenuto</a>", $html);
        self::assertStringContainsString('<main id="contenuto" class="flex flex-1 flex-col">', $html);
        self::assertStringContainsString('<header class="fs-bar fs-gutter mx-auto max-w-container border-b-0">', $html);
        self::assertStringContainsString('<footer class="border-t border-hairline">', $html);
        self::assertStringContainsString('<nav aria-label="Principale"', $html);
        self::assertStringContainsString('<nav aria-label="Contatti"', $html);
        // La cifra grande e i quadrati dei posti sono decorativi: il titolo li dice a parole.
        self::assertStringContainsString('<div class="fs-hero__numeral fs-anim-rise fs-step-2" aria-hidden="true">', $html);
        self::assertStringContainsString('<span class="fs-numeral fs-chrome fs-anim-sheen fs-step-20">5</span>', $html);
        self::assertStringContainsString('<div class="fs-hero__tally flex items-center gap-4 lg:gap-5" aria-hidden="true">', $html);
    }

    public function testTheSiteBarLinksToTheSections(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertStringContainsString('<a href="/" class="fs-bar__mark" aria-label="4Street, home">4Street</a>', $html);
        foreach (['#fasce' => 'Le fasce', '#spazio' => 'Lo spazio', '#domande' => 'Domande'] as $href => $label) {
            self::assertStringContainsString('<a href="' . $href . '" class="fs-btn fs-btn--ghost fs-btn--sm hidden lg:inline-flex"><span class="fs-btn__label">' . $label . '</span></a>', $html);
        }
        self::assertStringContainsString('<a href="#dove" class="fs-btn fs-btn--ghost fs-btn--sm -mr-2"><span class="fs-btn__label">Dove siamo</span></a>', $html);
    }

    public function testThePrimaryActionLeadsToTheGym(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        // "Vieni a trovarci" in apertura, nell'invito finale e nella barra; "Apri in Google Maps" nella mappa.
        self::assertSame(3, substr_count($html, '<span>Vieni a trovarci</span>'));
        self::assertStringContainsString('<span>Apri in Google Maps</span>', $html);
        self::assertSame(4, substr_count($html, '<a href="' . self::DIRECTIONS . '" target="_blank" rel="noopener" class="fs-btn fs-btn--primary'));
        // La barra osserva solo i bottoni oro del contenuto, non il proprio.
        self::assertSame(3, substr_count($html, 'data-primary-cta'));
        self::assertSame(3, substr_count($html, '<span>Scrivici su WhatsApp</span>'));
        self::assertStringContainsString('<a class="fs-text-link" href="' . self::WHATSAPP . '" target="_blank" rel="noopener">WhatsApp</a>', $html);
    }

    public function testTheBookingPreview(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertStringContainsString('<fieldset class="fs-slots" data-slot-picker>', $html);
        self::assertStringContainsString('<span class="fs-tag fs-tag--placeholder">[Fasce illustrative]</span>', $html);
        self::assertSame([
            '07:00' => '2/5',
            '09:30' => '4/5',
            '12:30' => '1/5',
            '17:30' => 'Completo',
            '18:30' => '3/5',
            '20:00' => '0/5',
        ], self::slots($html));
        self::assertSame(1, substr_count($html, ' checked>'));
        self::assertStringContainsString('<input type="radio" name="fascia" value="18:30" class="fs-slot__input" data-seat="4" checked>', $html);
        self::assertStringContainsString('<input type="radio" name="fascia" value="17:30" class="fs-slot__input" disabled>', $html);
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>12:30<span class="sr-only">, 1 posto occupato su 5</span>', $html);
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>17:30<span class="sr-only">, completa</span>', $html);
        // Un quadrato per posto: cinque in apertura, cinque per ognuna delle sei fasce.
        self::assertSame(5 + 6 * 5, preg_match_all('#<span class="fs-seat[" ]#', $html));
        self::assertSame(2 + 4 + 1 + 5 + 3 + 0, substr_count($html, '<span class="fs-seat fs-seat--taken">'));
        self::assertSame(5, substr_count($html, '<span class="fs-seat fs-seat--next">'));

        // Il riepilogo della fascia scelta: lo script lo aggiorna, i lettori di schermo lo leggono.
        self::assertStringContainsString('<div class="flex flex-col gap-2" aria-live="polite" aria-atomic="true">', $html);
        self::assertStringContainsString('data-slot-time>18:30</p>', $html);
        self::assertStringContainsString('<p class="text-body-sm text-fg-muted">Il tuo è il posto <span data-slot-seat>4</span> di 5.</p>', $html);

        // Prenotare dal sito non si può ancora.
        self::assertStringContainsString('<button type="button" class="fs-btn fs-btn--primary min-w-0 flex-1" aria-disabled="true" aria-describedby="prenota-presto">Prenota il posto</button>', $html);
        self::assertStringContainsString('<span id="prenota-presto" class="fs-tag">Presto</span>', $html);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testTheCapacityDrivesEveryNumber(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE, ['booking' => SiteFixture::booking([
            'capacity' => 6,
            'example_slots' => ['10:00' => 6, '19:00' => 2],
            'default_slot' => '19:00',
        ])])->body;

        self::assertStringContainsString('<span class="fs-numeral fs-chrome fs-anim-sheen fs-step-20">6</span>', $html);
        self::assertStringContainsString('<span class="sr-only">6 </span>persone per fascia oraria.', $html);
        self::assertSame(6, substr_count($html, '<span class="fs-seat"><span class="fs-seat__fill"></span></span>'));
        self::assertStringContainsString('tabular-nums">6/6</span>', $html);
        self::assertSame(['10:00' => 'Completo', '19:00' => '2/6'], self::slots($html));
        self::assertStringContainsString('Il tuo è il posto <span data-slot-seat>3</span> di 6.', $html);
        // Il testo editoriale resta com'è scritto.
        self::assertStringContainsString('>I posti sono cinque. Vieni a vederli.</h2>', $html);
    }

    public function testWhereWeAre(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertSame([
            'Indirizzo' => 'Via del Bando 22, San Marino',
            'Inaugurazione' => 'Domenica 6 dicembre 2026, ore 15:00',
            'Orari' => '[Orari da confermare]',
        ], self::details($html));
        self::assertStringContainsString('<svg class="fs-map__art" viewBox="0 0 800 500" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Mappa illustrativa: il percorso arriva a Via del Bando 22, San Marino">', $html);
        self::assertStringContainsString('<path class="fs-map__route" pathLength="1"', $html);
        self::assertStringContainsString('<span class="fs-map__flag fs-tag fs-tag--placeholder">Mappa illustrativa</span>', $html);
        self::assertStringContainsString('<span class="text-body-sm text-fg">Via del Bando 22, San Marino</span>', $html);
        self::assertStringContainsString('<a href="https://maps.apple.com/?daddr=Via%20del%20Bando%2022%2C%20San%20Marino" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-btn--block sm:w-auto">', $html);
        self::assertStringContainsString('data-copy="Via del Bando 22, San Marino" data-copy-status="copia-indirizzo-stato">', $html);
        self::assertStringContainsString('<span class="fs-btn__label" data-copy-label>Copia indirizzo</span>', $html);
        self::assertStringContainsString('<p id="copia-indirizzo-stato" class="sr-only" aria-live="polite"></p>', $html);
        // Nessun sito incorporato: la mappa è un disegno della pagina.
        self::assertStringNotContainsString('<iframe', $html);
    }

    public function testFrequentlyAskedQuestions(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertSame([
            'Quante persone ci sono in sala?',
            'Come prenoto?',
            'Dove siete?',
            'Quando aprite?',
            'Che orari fate?',
            'Quanto costa?',
        ], self::questions($html));
        self::assertSame(1, substr_count($html, '<details class="fs-faq__item" open>'));
        self::assertStringContainsString("<details class=\"fs-faq__item\" open>\n  <summary class=\"fs-faq__question\">\n    <span class=\"text-lead text-fg\">Quante persone ci sono in sala?</span>", $html);
        self::assertStringContainsString('<p class="fs-faq__answer text-body">Al massimo cinque per fascia oraria. Non una di più.</p>', $html);
        self::assertStringContainsString('<p class="fs-faq__answer text-body">In Via del Bando 22, San Marino.</p>', $html);
    }

    public function testFooterAndActionBar(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertStringContainsString('<a href="https://www.instagram.com/4street_sw" target="_blank" rel="noopener" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">', $html);
        self::assertStringContainsString('<a href="/links/" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">', $html);
        self::assertStringContainsString('<span class="text-body-sm text-fg-subtle">Shop online</span>', $html);
        self::assertStringContainsString('<p class="fs-micro normal-case text-fg-subtle">© 2026 4Street</p>', $html);

        // La barra delle azioni è l'ultimo elemento della pagina, prima dello script.
        self::assertMatchesRegularExpression(
            '#</footer>\s*<nav aria-label="Azioni rapide" class="fs-actionbar fs-anim-rise fs-step-12" data-action-bar>.*?</nav>\s*<script src="/assets/js/app.js\?v=' . SiteFixture::JS_MTIME . '" defer></script>\s*</body>#s',
            $html,
        );
        self::assertStringContainsString('<a href="' . self::WHATSAPP . '" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-btn--icon" aria-label="Scrivici su WhatsApp">', $html);
    }

    public function testSharedLayoutAndVersionedAssets(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertStringContainsString('<link rel="stylesheet" href="/assets/css/app.css?v=' . SiteFixture::CSS_MTIME . '">', $html);
        self::assertStringContainsString('<img src="/assets/img/logo.jpg?v=' . SiteFixture::LOGO_MTIME . '" alt="4Street" width="1254" height="1254" loading="lazy" decoding="async"', $html);
        self::assertStringContainsString('<meta property="og:image" content="https://fourstreet.studio/assets/img/logo.jpg">', $html);
        self::assertStringEndsWith("</script>\n</body>\n</html>\n", $html);
    }

    public function testStructuredDataWithOnlyKnownFacts(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        self::assertStringContainsString(
            '<script type="application/ld+json">{"@context":"https://schema.org","@type":"ExerciseGym","name":"4Street",'
            . '"url":"https://fourstreet.studio/","address":"Via del Bando 22, San Marino",'
            . '"sameAs":["https://www.instagram.com/4street_sw"]}</script>' . "\n</head>",
            $html,
        );
    }

    public function testNoInlineStylesAnywhere(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        // La Content-Security-Policy non ammette stili in linea: nessun attributo style, nessun <style>.
        self::assertStringNotContainsString(' style=', $html);
        self::assertStringNotContainsString('<style', $html);
        self::assertSame(1, substr_count($html, '<script src='));
    }

    public function testOneSecondBeforeTheOpening(): void
    {
        $html = SiteFixture::handle('GET', '/', '2026-12-06 14:59:59')->body;

        self::assertSame(['giorni' => '00', 'ore' => '00', 'minuti' => '00', 'secondi' => '01'], self::countdown($html));
        self::assertStringContainsString('<div class="fs-hero__state" data-state="open" hidden>', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function openingDayAndAfter(): iterable
    {
        yield 'all\'istante esatto' => [self::OPENING];
        yield 'la sera dell\'inaugurazione' => ['2026-12-06 21:00:00'];
        yield 'all\'ultimo secondo del giorno' => ['2026-12-06 23:59:59'];
        yield 'il giorno dopo' => [self::DAY_AFTER];
        yield 'mesi dopo' => ['2027-03-01 10:00:00'];
    }

    #[DataProvider('openingDayAndAfter')]
    public function testFromTheOpeningTheGymIsOpen(string $now): void
    {
        $html = SiteFixture::handle('GET', '/', $now)->body;

        self::assertStringContainsString('<title>4Street · Siamo aperti</title>', $html);
        self::assertStringContainsString('<meta name="description" content="4Street, palestra a San Marino. Cinque persone per fascia oraria, non una di più. Ti aspettiamo in Via del Bando 22, San Marino.">', $html);
        self::assertStringNotContainsString('data-countdown', $html);
        self::assertStringNotContainsString('data-state="pre"', $html);
        self::assertStringNotContainsString(' hidden>', $html);
        self::assertStringContainsString('<div class="fs-hero__state" data-state="open">', $html);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event fs-anim-rise fs-step-8"><span class="fs-tag__dot fs-anim-breathe"></span>Aperti</span>', $html);
        self::assertStringContainsString('lg:text-lead">Siamo aperti in Via del Bando 22, San Marino. Ti aspettiamo.</p>', $html);
        self::assertStringContainsString('San Marino · <span data-state="open">Aperti</span></span>', $html);
        self::assertStringContainsString('Via del Bando 22, San Marino. <span data-state="open">Siamo aperti.</span></p>', $html);
        self::assertStringContainsString('<span data-state="open">Siamo aperti. Abbiamo inaugurato domenica 6 dicembre 2026.</span>', $html);
        self::assertSame('Domenica 6 dicembre 2026', self::details($html)['Inaugurazione'] ?? null);
        self::assertStringNotContainsString('/calendario.php', $html);
        self::assertStringNotContainsString('In corso', $html);
    }

    public function testMapPreviewShowsTheOpenStateBeforeTheOpening(): void
    {
        $html = SiteFixture::handle('GET', '/?anteprima=mappa', self::BEFORE)->body;

        self::assertStringContainsString('<title>4Street · Siamo aperti</title>', $html);
        self::assertStringNotContainsString('data-countdown', $html);
        self::assertStringContainsString('<div class="fs-hero__state" data-state="open">', $html);
    }

    public function testZeroPreviewEndsTheCountdownInTwelveSeconds(): void
    {
        $html = SiteFixture::handle('GET', '/?anteprima=zero', self::BEFORE)->body;

        self::assertStringContainsString('data-target="1795590012000"', $html);
        self::assertStringContainsString('data-now="1795590000000"', $html);
        self::assertSame(['giorni' => '00', 'ore' => '00', 'minuti' => '00', 'secondi' => '12'], self::countdown($html));
        self::assertStringContainsString('<title>4Street · Apriamo domenica 6 dicembre</title>', $html);
        self::assertStringContainsString('<div class="fs-hero__state" data-state="open" hidden>', $html);
    }

    public function testZeroPreviewAfterTheEventStillCountsDownToTheOpenState(): void
    {
        $html = SiteFixture::handle('GET', '/?anteprima=zero', '2026-12-10 18:00:00')->body;

        self::assertSame(['giorni' => '00', 'ore' => '00', 'minuti' => '00', 'secondi' => '12'], self::countdown($html));
        self::assertStringContainsString('data-open-title="4Street · Siamo aperti"', $html);
        self::assertStringContainsString('<div class="fs-hero__state" data-state="open" hidden>', $html);
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
        $html = SiteFixture::handle('GET', '/', self::BEFORE, ['opening_time_confirmed' => false])->body;

        self::assertStringNotContainsString('15:00', $html);
        self::assertStringContainsString('lg:text-lead">Inaugurazione domenica 6 dicembre 2026.</p>', $html);
        self::assertStringContainsString('<span data-state="pre">Inauguriamo domenica 6 dicembre 2026.</span>', $html);
        self::assertSame('Domenica 6 dicembre 2026', self::details($html)['Inaugurazione'] ?? null);
    }

    public function testPlaceholdersForTheMissingContent(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE)->body;

        foreach (self::PLACEHOLDERS as $placeholder) {
            self::assertStringContainsString($placeholder, $html);
        }
        self::assertSame(3, substr_count($html, '<figure class="fs-photo">'));
        self::assertSame(3, substr_count($html, '<span class="fs-photo__placeholder fs-micro normal-case">'));
    }

    public function testWithoutPlaceholdersTheMissingContentIsLeftOut(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE, ['show_placeholders' => false])->body;

        foreach (self::PLACEHOLDERS as $placeholder) {
            self::assertStringNotContainsString($placeholder, $html);
        }
        self::assertStringNotContainsString('class="fs-ph"', $html);
        self::assertStringNotContainsString('Cosa trovi in sala', $html);
        self::assertStringNotContainsString('fs-photos', $html);
        self::assertSame(['Quante persone ci sono in sala?', 'Come prenoto?', 'Dove siete?', 'Quando aprite?'], self::questions($html));
        self::assertSame(['Indirizzo', 'Inaugurazione'], array_keys(self::details($html)));
        // Quello che non è un dato mancante resta: le fasce di esempio e la mappa illustrativa.
        self::assertStringContainsString('<span class="fs-tag fs-tag--placeholder">[Fasce illustrative]</span>', $html);
        self::assertStringContainsString('<span class="fs-map__flag fs-tag fs-tag--placeholder">Mappa illustrativa</span>', $html);
        self::assertMatchesRegularExpression('#lg:pt-6">\s*<p class="fs-micro normal-case text-fg-subtle">© 2026 4Street</p>#', $html);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function placeholderSwitch(): iterable
    {
        yield 'con i segnaposto' => [true];
        yield 'senza segnaposto' => [false];
    }

    #[DataProvider('placeholderSwitch')]
    public function testTheContentOnceItArrives(bool $showPlaceholders): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE, [
            'show_placeholders' => $showPlaceholders,
            'content' => [
                'hours' => 'Dal lunedì al sabato, 7:00-22:00',
                'prices' => 'Dieci ingressi <da> 100 euro',
                'room' => 'pesi liberi e rack',
                'photos' => [
                    'room' => ['src' => '/assets/img/sala.jpg', 'width' => 1600, 'height' => 1200, 'alt' => 'La sala, in bianco e nero.'],
                    'entrance' => ['src' => '/assets/img/ingresso.webp', 'width' => 1200, 'height' => 1500, 'alt' => 'L\'ingresso di 4Street.'],
                    'detail' => ['src' => '/assets/img/dettaglio.jpg', 'width' => 1200, 'height' => 1200, 'alt' => 'Un dettaglio della sala.'],
                ],
                'privacy_url' => 'https://fourstreet.studio/privacy/',
                'company' => '4Street S.r.l. · COE SM00000',
            ],
        ])->body;

        self::assertStringNotContainsString('class="fs-ph"', $html);
        self::assertStringNotContainsString('fs-photo__placeholder', $html);
        self::assertSame('Dal lunedì al sabato, 7:00-22:00', self::details($html)['Orari'] ?? null);
        self::assertStringContainsString('<p class="fs-faq__answer text-body">Dal lunedì al sabato, 7:00-22:00</p>', $html);
        self::assertStringContainsString('<p class="fs-faq__answer text-body">Dieci ingressi &lt;da&gt; 100 euro</p>', $html);
        self::assertStringContainsString('<p class="max-w-content text-body text-fg-muted">Cosa trovi in sala: pesi liberi e rack.</p>', $html);
        self::assertStringContainsString('<img src="/assets/img/sala.jpg" alt="La sala, in bianco e nero." width="1600" height="1200" loading="lazy" decoding="async" class="fs-photo__image">', $html);
        self::assertStringContainsString('<img src="/assets/img/ingresso.webp" alt="L&#039;ingresso di 4Street." width="1200" height="1500" loading="lazy" decoding="async" class="fs-photo__image">', $html);
        self::assertSame(3, substr_count($html, 'class="fs-photo__image"'));
        self::assertStringContainsString('<a href="https://fourstreet.studio/privacy/" class="fs-text-link inline-flex min-h-11 items-center text-body-sm">Informativa privacy</a>', $html);
        self::assertStringContainsString('<p class="text-body-sm text-fg-subtle">4Street S.r.l. · COE SM00000</p>', $html);
        self::assertCount(6, self::questions($html));
    }

    public function testWithoutPlaceholdersOnlyThePhotosThatExistAreShown(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE, [
            'show_placeholders' => false,
            'content' => ['photos' => ['entrance' => ['src' => '/assets/img/ingresso.jpg', 'width' => 1200, 'height' => 900, 'alt' => 'L\'ingresso.']]],
        ])->body;

        self::assertSame(1, substr_count($html, '<figure class="fs-photo">'));
        self::assertStringContainsString('<figcaption class="text-body-sm text-fg-subtle">L&#039;ingresso, in Via del Bando 22, San Marino.</figcaption>', $html);
        self::assertStringNotContainsString('La sala.</figcaption>', $html);
    }

    public function testOptionalLinksAndCustomMap(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE, [
            'links' => ['instagram' => '', 'whatsapp' => ''],
            'maps_url' => 'https://maps.app.goo.gl/AbC123?g=1&h=2',
            'site_url' => '',
        ])->body;

        self::assertStringNotContainsString('WhatsApp', str_replace('Oggi su WhatsApp.', '', $html));
        self::assertStringNotContainsString('wa.me', $html);
        self::assertStringNotContainsString('instagram', $html);
        self::assertStringNotContainsString('og:image', $html);
        self::assertStringContainsString('<p class="fs-faq__answer text-body">La prenotazione della fascia dal sito arriva presto.</p>', $html);
        self::assertSame(4, substr_count($html, '<a href="https://maps.app.goo.gl/AbC123?g=1&amp;h=2" target="_blank" rel="noopener" class="fs-btn fs-btn--primary'));
        self::assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"ExerciseGym","name":"4Street","address":"Via del Bando 22, San Marino"}</script>', $html);
    }

    public function testTheAddressIsEscapedEverywhere(): void
    {
        $html = SiteFixture::handle('GET', '/', self::BEFORE, ['address' => 'Via "Nuova" 1 & <b>Borgo</b>'])->body;

        self::assertStringNotContainsString('<b>Borgo</b>', $html);
        self::assertStringContainsString('data-copy="Via &quot;Nuova&quot; 1 &amp; &lt;b&gt;Borgo&lt;/b&gt;"', $html);
        self::assertStringContainsString('aria-label="Mappa illustrativa: il percorso arriva a Via &quot;Nuova&quot; 1 &amp; &lt;b&gt;Borgo&lt;/b&gt;"', $html);
        self::assertStringContainsString('destination=Via%20%22Nuova%22%201%20%26%20%3Cb%3EBorgo%3C%2Fb%3E', $html);
        self::assertStringContainsString('"address":"Via \u0022Nuova\u0022 1 \u0026 \u003Cb\u003EBorgo\u003C/b\u003E"', $html);
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
        $policy = (string) $response->header('Content-Security-Policy');

        self::assertStringContainsString("script-src 'self'", $policy);
        self::assertStringContainsString("style-src 'self' https://fonts.googleapis.com", $policy);
        self::assertStringContainsString("frame-src 'none'", $policy);
        self::assertStringNotContainsString('unsafe-inline', $policy);
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
            preg_match_all('#<span class="fs-cd__new fs-chrome">(\d)</span>#', $unit[2], $cells);
            $digits[$unit[1]] = implode('', $cells[1]);
        }

        return $digits;
    }

    /**
     * Le righe dell'elenco di "Dove siamo", con il testo che si legge (segnaposto compresi).
     *
     * @return array<string, string>
     */
    private static function details(string $html): array
    {
        preg_match_all('#<dt class="fs-label">([^<]+)</dt>\s*<dd>(.*?)</dd>#s', $html, $rows);

        return array_combine($rows[1], array_map(strip_tags(...), $rows[2]));
    }

    /**
     * Le domande frequenti, nell'ordine.
     *
     * @return list<string>
     */
    private static function questions(string $html): array
    {
        preg_match_all('#<summary class="fs-faq__question">\s*<span class="text-lead text-fg">([^<]+)</span>#', $html, $questions);

        return $questions[1];
    }

    /**
     * Le fasce di esempio: orario => posti presi ("3/5"), oppure "Completo".
     *
     * @return array<string, string>
     */
    private static function slots(string $html): array
    {
        preg_match_all('#<input type="radio" name="fascia" value="([\d:]+)".*?<span class="fs-slot__status[^"]*" aria-hidden="true">([^<]+)</span>#s', $html, $slots);

        return array_combine($slots[1], $slots[2]);
    }
}
