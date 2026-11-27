<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Booking;

use FourStreet\Application;
use FourStreet\Booking\Landing\LivePreview;
use FourStreet\Booking\Landing\LivePreviewSource;
use FourStreet\Booking\SlotBoard;
use FourStreet\Controller\HomeController;
use FourStreet\Controller\LinksController;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La landing con le prenotazioni attive: le fasce vere del primo giorno con un posto libero, i testi
 * al presente, "Prenota il posto" che porta alla conferma della fascia scelta (un modulo GET, anche
 * senza JavaScript) e il link fisso all'area clienti. La pagina è uguale per tutti e non va in cache.
 */
#[CoversClass(Application::class)]
#[CoversClass(HomeController::class)]
#[CoversClass(LinksController::class)]
#[CoversClass(LivePreviewSource::class)]
#[CoversClass(LivePreview::class)]
#[CoversClass(SlotBoard::class)]
final class LandingBookingTest extends TestCase
{
    public function testTheRealSlotsOfTheFirstBookableDay(): void
    {
        $fixture = new AccountFixture();
        $fixture->book($fixture->customer('anna@example.com'), '2026-12-07', '18:30');

        $home = $fixture->browser()->get('/');
        $html = $home->body;

        self::assertSame(200, $home->status);
        self::assertSame('no-cache', $home->header('Cache-Control'));
        self::assertSame([], TestBrowser::setCookieHeaders($home), 'La landing non apre sessioni né dà cookie.');
        self::assertStringContainsString('<meta name="description" content="4Street, palestra a San Marino. Cinque persone per fascia oraria: prenota la tua dal sito. Inaugurazione domenica 6 dicembre in Via del Bando 22, San Marino.">', $html);

        // I testi al presente; niente "[Fasce illustrative]" né "Presto".
        self::assertStringContainsString('>Così prenoti il tuo posto.</h2>', $html);
        self::assertStringContainsString('>Qui sotto le fasce di lunedì 7 dicembre, con i posti liberi. Gli altri giorni sono nella pagina delle prenotazioni.</p>', $html);
        self::assertStringNotContainsString('[Fasce illustrative]', $html);
        self::assertStringNotContainsString('prenota-presto', $html);
        self::assertStringNotContainsString('Dal sito, presto.', $html);
        self::assertStringContainsString('<p class="text-body-sm text-fg-muted">Accedi o crea l&#039;account e conferma: ti arriva un&#039;email.</p>', $html);

        // Il modulo GET verso la conferma: il giorno nascosto, la fascia dalle radio, un bottone vero.
        self::assertStringContainsString('<form method="get" action="/prenota/conferma/" class="fs-split fs-split--reverse items-start">', $html);
        self::assertStringContainsString('<input type="hidden" name="giorno" value="2026-12-07">', $html);
        self::assertStringContainsString('<span class="fs-label text-fg-muted">Lunedì 7 dicembre</span>', $html);
        self::assertStringContainsString('<input type="radio" name="fascia" value="07:00" class="fs-slot__input" data-seat="1" checked>', $html);
        self::assertStringContainsString('<input type="radio" name="fascia" value="18:30" class="fs-slot__input" data-seat="2">', $html);
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>18:30<span class="sr-only">, 1 posto occupato su 5</span>', $html);
        self::assertStringContainsString('tabular-nums" aria-hidden="true">1/5</span>', $html);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block" data-primary-cta>Prenota il posto</button>', $html);
        self::assertStringContainsString('data-slot-time>07:00</p>', $html);
        self::assertStringContainsString('Il tuo è il posto <span data-slot-seat>1</span> di 5.', $html);

        // Gli altri giorni e le prenotazioni, e l'area clienti nella barra e nel piè di pagina.
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-07" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">', $html);
        self::assertSame(3, substr_count($html, '<a href="/account/" class="fs-btn fs-btn--ghost fs-btn--sm'));
        self::assertStringContainsString('<span class="fs-btn__label">Area clienti</span>', $html);

        // "Come prenoto?" spiega come si fa.
        self::assertStringContainsString('Scegli la fascia <a class="fs-text-link" href="/prenota/">tra quelle dei prossimi giorni</a>, accedi (o crea l\'account) e conferma', $html);

        // La scelta della landing porta dritta al riepilogo.
        $browser = $fixture->browser();
        $fixture->registerAndVerify($browser);
        self::assertStringContainsString('Lunedì 7 dicembre, ore 07:00.</h1>', $browser->get('/prenota/conferma/?giorno=2026-12-07&fascia=07%3A00')->body);
    }

    public function testTheLandingIsTheSameForEveryVisitor(): void
    {
        $fixture = new AccountFixture();
        $member = $fixture->browser();
        $fixture->registerAndVerify($member);

        self::assertSame($fixture->browser('198.51.100.30')->get('/')->body, $member->get('/')->body);
    }

    public function testOnceTodaysSlotsHaveStartedTheLandingShowsTheNextDay(): void
    {
        $fixture = new AccountFixture(now: '2026-12-07 12:00:00');

        $noon = $fixture->browser()->get('/')->body;
        self::assertStringContainsString('<input type="hidden" name="giorno" value="2026-12-07">', $noon);
        self::assertStringContainsString('<input type="radio" name="fascia" value="09:30" class="fs-slot__input" disabled>', $noon);
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>09:30<span class="sr-only">, prenotazioni chiuse</span>', $noon);
        self::assertStringContainsString('<span class="fs-slot__status fs-label text-fg-subtle" aria-hidden="true">Chiusa</span>', $noon);
        self::assertStringContainsString('<input type="radio" name="fascia" value="12:30" class="fs-slot__input" data-seat="1" checked>', $noon);

        $fixture->clock->moveTo('2026-12-07 20:00:00');
        $evening = $fixture->browser()->get('/')->body;
        self::assertStringContainsString('<input type="hidden" name="giorno" value="2026-12-08">', $evening);
        self::assertStringContainsString('Qui sotto le fasce di martedì 8 dicembre', $evening);
    }

    public function testAFullDayIsSkipped(): void
    {
        $fixture = new AccountFixture();
        self::fill($fixture, '2026-12-07');

        self::assertStringContainsString('<input type="hidden" name="giorno" value="2026-12-08">', $fixture->browser()->get('/')->body);
    }

    public function testWhenEverySlotIsFullTheLandingSaysSoAndLinksTheDays(): void
    {
        // Prenotabile solo lunedì 7 (oggi è il 25 novembre, più 12 giorni): tutto pieno.
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['days_ahead' => 12])]);
        self::fill($fixture, '2026-12-07');

        $html = $fixture->browser()->get('/')->body;

        self::assertStringNotContainsString('<form method="get"', $html);
        self::assertStringNotContainsString(' checked>', $html);
        self::assertSame(6, substr_count($html, 'class="fs-slot__input" disabled>'));
        self::assertStringContainsString('Le fasce dei prossimi giorni sono al completo. Si libera un posto quando qualcuno disdice.', $html);
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-07" class="fs-btn fs-btn--primary fs-btn--block" data-primary-cta>', $html);
        self::assertStringContainsString('<span>Vedi tutti i giorni</span>', $html);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function nothingBookable(): iterable
    {
        // Si prenota solo oggi, e alle 20:30 le fasce di oggi sono tutte iniziate.
        yield 'solo oggi, di sera' => [['days_ahead' => 0], '2026-12-07 20:30:00'];
        // Fino a domenica 6, il giorno dell'inaugurazione, che è chiuso.
        yield 'solo la domenica chiusa' => [['days_ahead' => 11], '2026-11-25 08:00:00'];
    }

    /**
     * @param array<string, mixed> $rules
     */
    #[DataProvider('nothingBookable')]
    public function testWhenNothingCanBeBookedTheLandingDoesNotPretend(array $rules, string $now): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking($rules)], now: $now);

        $html = $fixture->browser()->get('/')->body;

        self::assertStringContainsString('Nessuna fascia da prenotare nei prossimi giorni.', $html);
        self::assertStringNotContainsString('con i posti liberi', $html);
        self::assertStringNotContainsString('al completo', $html);
        self::assertStringNotContainsString('fs-slot__input', $html);
    }

    public function testBeforeTheFirstBookableDayTheLandingSaysSo(): void
    {
        // Oggi più cinque giorni: l'inaugurazione è ancora oltre.
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['days_ahead' => 5])]);

        $html = $fixture->browser()->get('/')->body;

        self::assertStringContainsString('>Le prossime fasce non si prenotano ancora. Per il tuo posto, intanto, scrivici.</p>', $html);
        self::assertStringContainsString('Nessuna fascia da prenotare nei prossimi giorni.', $html);
        self::assertStringNotContainsString('fs-slot__input', $html);
        self::assertStringContainsString('<span>Scrivici su WhatsApp</span>', $html);
        self::assertStringContainsString('Le prossime fasce non si prenotano ancora. Torna a trovarci fra qualche giorno.', $fixture->browser()->get('/prenota/')->body);
    }

    private static function fill(AccountFixture $fixture, string $date): void
    {
        foreach (['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'] as $time) {
            foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
                $fixture->book($fixture->customer($name . str_replace(':', '', $time) . $date . '@example.com'), $date, $time);
            }
        }
    }
}
