<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Account\User;
use FourStreet\Admin\AdminModule;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Http\MonthController;
use FourStreet\Admin\Http\StaffBlockedPage;
use FourStreet\Application;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\SlotCounts;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\Month;
use FourStreet\Booking\Staff\MonthDay;
use FourStreet\Booking\Staff\MonthOverview;
use FourStreet\Booking\Staff\MonthSheet;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Il calendario del mese nel pannello del titolare, attraverso l'applicazione vera: ogni giorno con l'orario (aperto,
 * chiuso con la nota del titolare, cambiato, prima dell'inaugurazione), le fasce, le persone prenotate, le fasce
 * piene e le prenotazioni fuori dall'orario; oggi, i giorni passati, il mese prima e dopo fino al bordo della portata
 * del pannello, e un mese scritto male. L'orologio parte da mercoledì 25 novembre 2026, 08:00; l'inaugurazione è
 * domenica 6 dicembre alle 15:00, la domenica è chiusa.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(AdminUrls::class)]
#[CoversClass(MonthController::class)]
#[CoversClass(StaffBlockedPage::class)]
#[CoversClass(MonthOverview::class)]
#[CoversClass(MonthSheet::class)]
#[CoversClass(MonthDay::class)]
#[CoversClass(Month::class)]
#[CoversClass(PdoBookingRepository::class)]
final class AdminCalendarTest extends TestCase
{
    private AccountFixture $fixture;

    private User $admin;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        // Martedì 8 dicembre è uno dei closed_dates del file.
        $this->fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['closed_dates' => ['2026-12-08']])]);
        $this->admin = $this->fixture->admin();
        $this->browser = $this->fixture->browser();
        $this->fixture->logIn($this->browser, $this->admin->email->value);
    }

    public function testTheMonthOfTodayWithTheDaysBeforeTheOpening(): void
    {
        $page = $this->browser->get('/gestione/calendario/');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('<title>4Street · Gestione · Novembre 2026</title>', $page->body);
        self::assertStringContainsString('Novembre 2026.</h1>', $page->body);
        self::assertStringContainsString('>Nessuna prenotazione nel mese.</p>', $page->body);
        self::assertStringNotContainsString('fs-alert', $page->body, 'Niente da guardare.');
        // La navigazione del pannello: è proprio la pagina del link "Calendario".
        self::assertStringContainsString('<a href="/gestione/calendario/" class="fs-adminnav__link" aria-current="page">Calendario</a>', $page->body);

        // Un giorno per riga, divisi per settimana da lunedì a domenica: il 1° novembre è una domenica, il 30 un lunedì.
        self::assertSame(30, substr_count($page->body, '<a href="/gestione/?giorno=2026-11-'));
        self::assertSame(6, substr_count($page->body, '<section class="fs-month__week"'));
        self::assertStringContainsString('<h2 id="settimana-2026-11-01" class="fs-month__title fs-label">Domenica 1 novembre</h2>', $page->body);
        self::assertStringContainsString('<h2 id="settimana-2026-11-02" class="fs-month__title fs-label">Dal 2 all&#039;8 novembre</h2>', $page->body);
        self::assertStringContainsString('<h2 id="settimana-2026-11-09" class="fs-month__title fs-label">Dal 9 al 15 novembre</h2>', $page->body);
        self::assertStringContainsString('<h2 id="settimana-2026-11-30" class="fs-month__title fs-label">Lunedì 30 novembre</h2>', $page->body);

        // Oggi è segnato (aria-current="date" e il segno "Oggi"); prima dell'inaugurazione nessuna fascia.
        $today = self::day($page->body, '2026-11-25');
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-11-25" class="fs-month__day fs-month__day--off" aria-current="date">', $today);
        self::assertStringContainsString('<span class="sr-only">Mercoledì 25 novembre: prima dell&#039;inaugurazione</span>', $today);
        self::assertStringContainsString('<span class="fs-month__main">Prima dell&#039;inaugurazione</span>', $today);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event">Oggi</span>', $today);
        self::assertSame(1, substr_count($page->body, 'aria-current="date"'));
        // I giorni passati si fanno da parte, ma si aprono ancora.
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-11-24" class="fs-month__day fs-month__day--off fs-month__day--past">', $page->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-11-26" class="fs-month__day fs-month__day--off">', $page->body);

        // Il mese prima e quello dopo; "Questo mese" no, è già questo.
        self::assertStringContainsString('<nav class="fs-daynav fs-month__nav" aria-label="Mese prima e dopo">', $page->body);
        self::assertStringContainsString('<a href="/gestione/calendario/?mese=2026-10" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step">', $page->body);
        self::assertStringContainsString('<span><span class="sr-only">Mese prima: </span>ott<span class="sr-only">, ottobre 2026</span></span>', $page->body);
        self::assertStringContainsString('<a href="/gestione/calendario/?mese=2026-12" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step fs-daynav__step--next">', $page->body);
        self::assertStringContainsString('<span><span class="sr-only">Mese dopo: </span>dic<span class="sr-only">, dicembre 2026</span></span>', $page->body);
        self::assertStringNotContainsString('Questo mese', $page->body);

        self::assertSame($page->body, $this->browser->get('/gestione/calendario/?mese=2026-11')->body, 'Il mese di oggi, scritto o no.');
    }

    public function testEveryDayOfTheMonthSaysHowItIsAndWhoIsBooked(): void
    {
        $schedule = new PdoSchedule($this->fixture->database, $this->fixture->site->booking);
        $now = $this->fixture->clock->now();
        // Il titolare chiude il 24 ("ferie") e il 23 lascia solo due fasce; il cambio dell'orario non tocca le prenotazioni.
        $schedule->saveDay(self::date('2026-12-24'), true, [], 'ferie', $this->admin->id, $now);
        $schedule->saveDay(self::date('2026-12-23'), false, [self::time('07:00'), self::time('09:30')], null, $this->admin->id, $now);
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->fixture->book($this->fixture->customer($name . '@example.test'), '2026-12-07', '18:30');
        }
        $this->fixture->book($this->fixture->customer('f@example.test'), '2026-12-07', '07:00');
        $this->fixture->bookGuest($this->admin, 'Marco Neri', '2026-12-07', '07:00');
        // Due prenotazioni rimaste in fasce che l'orario non ha più.
        $this->fixture->bookOutsideSchedule($this->fixture->customer('g@example.test'), '2026-12-23', '18:30');
        $this->fixture->bookOutsideSchedule($this->fixture->customer('h@example.test'), '2026-12-24', '18:30');

        $page = $this->browser->get('/gestione/calendario/?mese=2026-12');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('Dicembre 2026.</h1>', $page->body);
        self::assertStringContainsString('>9 prenotazioni nel mese.</p>', $page->body);
        // Le prenotazioni fuori dall'orario: l'avviso da guardare, in testa.
        self::assertStringContainsString('<p class="fs-alert__title">Da guardare</p>', $page->body);
        self::assertStringContainsString('<p>2 prenotazioni sono in fasce che l&#039;orario non ha più. Guardale nei giorni segnati «fuori orario»: da lì le disdici.</p>', $page->body);
        self::assertLessThan(strpos($page->body, 'fs-daynav'), strpos($page->body, 'Da guardare'));
        // Non è il mese di oggi: "Calendario" segna la sezione, e c'è "Questo mese".
        self::assertStringContainsString('<a href="/gestione/calendario/" class="fs-adminnav__link" aria-current="true">Calendario</a>', $page->body);
        self::assertStringContainsString('<a href="/gestione/calendario/" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__today">Questo mese</a>', $page->body);
        self::assertStringContainsString('<h2 id="settimana-2026-12-01" class="fs-month__title fs-label">Dal 1 al 6 dicembre</h2>', $page->body);
        self::assertStringContainsString('<h2 id="settimana-2026-12-28" class="fs-month__title fs-label">Dal 28 al 31 dicembre</h2>', $page->body);

        // Prima dell'inaugurazione; il giorno dell'inaugurazione è domenica, chiuso.
        self::assertStringContainsString('<span class="sr-only">Sabato 5 dicembre: prima dell&#039;inaugurazione</span>', $page->body);
        $opening = self::day($page->body, '2026-12-06');
        self::assertStringContainsString('<span class="sr-only">Domenica 6 dicembre: chiuso</span>', $opening);
        self::assertStringContainsString('<span class="fs-month__main">Chiuso</span>', $opening);

        // Un giorno aperto: quante persone e quante fasce, il segno della fascia piena.
        $monday = self::day($page->body, '2026-12-07');
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-07" class="fs-month__day">', $monday);
        self::assertStringContainsString('<span class="sr-only">Lunedì 7 dicembre: 6 fasce, 7 prenotati, una fascia piena</span>', $monday);
        self::assertStringContainsString('<span class="fs-month__main">7 prenotati</span>', $monday);
        self::assertStringContainsString('<span class="fs-month__meta">6 fasce</span>', $monday);
        self::assertStringContainsString('<span class="fs-month__marks"><span class="fs-tag">1 piena</span></span>', $monday);
        self::assertStringContainsString('<span class="fs-day__weekday">lun</span>', $monday);
        self::assertStringContainsString('<span class="fs-day__number">7</span>', $monday);

        // Un closed_dates del file: chiuso, senza nota.
        $closedByFile = self::day($page->body, '2026-12-08');
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-08" class="fs-month__day fs-month__day--off">', $closedByFile);
        self::assertStringContainsString('<span class="sr-only">Martedì 8 dicembre: chiuso</span>', $closedByFile);
        self::assertStringNotContainsString('fs-month__meta', $closedByFile);

        self::assertStringContainsString('<span class="sr-only">Mercoledì 9 dicembre: 6 fasce, nessuno prenotato</span>', $page->body);
        self::assertStringContainsString('<span class="fs-month__main">Nessuno prenotato</span>', self::day($page->body, '2026-12-09'));

        // L'orario cambiato per un giorno, con una prenotazione nella fascia tolta: il segno più evidente.
        $changed = self::day($page->body, '2026-12-23');
        self::assertStringContainsString('<span class="sr-only">Mercoledì 23 dicembre: 2 fasce, 1 prenotato, orario cambiato, una prenotazione fuori dall&#039;orario</span>', $changed);
        self::assertStringContainsString('<span class="fs-month__meta">2 fasce · orario cambiato</span>', $changed);
        self::assertStringContainsString('<span class="fs-tag fs-month__warn">1 fuori orario</span>', $changed);

        // La chiusura del titolare, con la sua nota (il pannello è solo suo), e la prenotazione che vi è rimasta.
        $closed = self::day($page->body, '2026-12-24');
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-24" class="fs-month__day fs-month__day--off">', $closed);
        self::assertStringContainsString('<span class="sr-only">Giovedì 24 dicembre: chiuso, ferie, una prenotazione fuori dall&#039;orario</span>', $closed);
        self::assertStringContainsString('<span class="fs-month__main">Chiuso</span>', $closed);
        self::assertStringContainsString('<span class="fs-month__meta">ferie</span>', $closed);
        self::assertStringContainsString('<span class="fs-tag fs-month__warn">1 fuori orario</span>', $closed);

        // Ogni giorno del mese c'è, una volta, e porta al suo giorno; nessun dato personale nella pagina.
        self::assertSame(31, substr_count($page->body, '<a href="/gestione/?giorno=2026-12-'));
        self::assertStringNotContainsString('example.test', $page->body);
        self::assertStringNotContainsString('Marco Neri', $page->body);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testADayWithEverySlotFull(): void
    {
        foreach (['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'] as $time) {
            foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
                $this->fixture->book($this->fixture->customer($name . str_replace(':', '', $time) . '@example.test'), '2026-12-09', $time);
            }
        }

        $day = self::day($this->browser->get('/gestione/calendario/?mese=2026-12')->body, '2026-12-09');

        self::assertStringContainsString('<span class="sr-only">Mercoledì 9 dicembre: 6 fasce, 30 prenotati, 6 fasce piene</span>', $day);
        self::assertStringContainsString('<span class="fs-tag">Tutto pieno</span>', $day);
    }

    public function testPastDaysAreQuieterTodayIsMarkedAndBothStillOpen(): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $this->fixture->clock->moveTo('2026-12-09 10:00:00');

        $page = $this->browser->get('/gestione/calendario/');

        self::assertStringContainsString('Dicembre 2026.</h1>', $page->body);
        self::assertStringContainsString('<a href="/gestione/calendario/" class="fs-adminnav__link" aria-current="page">Calendario</a>', $page->body);
        $past = self::day($page->body, '2026-12-07');
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-07" class="fs-month__day fs-month__day--past">', $past);
        self::assertStringContainsString('<span class="sr-only">Lunedì 7 dicembre: 6 fasce, 1 prenotato</span>', $past);
        $today = self::day($page->body, '2026-12-09');
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-09" class="fs-month__day" aria-current="date">', $today);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event">Oggi</span>', $today);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-10" class="fs-month__day">', $page->body);

        // Il giorno passato si apre, da guardare soltanto.
        self::assertStringContainsString('Giorno passato: si guarda soltanto.</p>', $this->browser->get('/gestione/?giorno=2026-12-07')->body);
    }

    public function testThePanelReachesTenYearsEachWayAndNoFurther(): void
    {
        $first = $this->browser->get('/gestione/calendario/?mese=2016-01');
        self::assertSame(200, $first->status);
        self::assertStringContainsString('Gennaio 2016.</h1>', $first->body);
        self::assertStringNotContainsString('Mese prima: ', $first->body);
        self::assertStringContainsString('<a href="/gestione/calendario/?mese=2016-02" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step fs-daynav__step--next">', $first->body);

        $last = $this->browser->get('/gestione/calendario/?mese=2036-12');
        self::assertSame(200, $last->status);
        self::assertStringContainsString('Dicembre 2036.</h1>', $last->body);
        self::assertStringContainsString('<a href="/gestione/calendario/?mese=2036-11" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step">', $last->body);
        self::assertStringNotContainsString('Mese dopo: ', $last->body);
        self::assertStringContainsString('<a href="/gestione/calendario/" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__today">Questo mese</a>', $last->body);

        foreach (['2015-12', '2037-01'] as $month) {
            $page = $this->browser->get('/gestione/calendario/?mese=' . $month);
            self::assertSame(404, $page->status, $month);
            self::assertStringContainsString('Fascia non trovata.</h1>', $page->body);
        }
        self::assertSame([], $this->fixture->reporter->reported);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongMonths(): iterable
    {
        yield 'a parole' => ['dicembre'];
        yield 'mese tredici' => ['2026-13'];
        yield 'mese zero' => ['2026-00'];
        yield 'senza lo zero' => ['2026-1'];
        yield 'un giorno' => ['2026-12-01'];
        yield 'vuoto' => [''];
        yield 'anno zero' => ['0000-01'];
        yield 'fine del calendario' => ['9999-12'];
        yield 'testo qualunque' => ['<script>'];
    }

    #[DataProvider('wrongMonths')]
    public function testAWronglyWrittenMonthIsNotFound(string $month): void
    {
        $page = $this->browser->get('/gestione/calendario/?mese=' . rawurlencode($month));

        self::assertSame(404, $page->status);
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('Fascia non trovata.</h1>', $page->body);
        self::assertStringContainsString('<a href="/gestione/" class="fs-btn fs-btn--primary fs-btn--block">Torna alla gestione</a>', $page->body);
        self::assertStringNotContainsString('<script>', $page->body);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testHeadAnswersLikeGet(): void
    {
        self::assertSame(200, $this->browser->head('/gestione/calendario/?mese=2026-12')->status);
        self::assertSame(404, $this->browser->head('/gestione/calendario/?mese=2026-13')->status);
    }

    public function testTheBookingsOfTheWholeMonthAreReadOnce(): void
    {
        // Il mese senza database per l'orario (il file) e con un repository che conta: una lettura sola delle prenotazioni,
        // già contate per fascia, e nessun'altra (mai una per giorno).
        $bookings = $this->createMock(BookingRepository::class);
        $bookings->expects(self::once())
            ->method('slotCounts')
            ->with(self::date('2026-12-01'), self::date('2026-12-31'))
            ->willReturn(new SlotCounts(['2026-12-07' => ['18:30' => 5, '12:00' => 1]]));
        $bookings->expects(self::never())->method(self::logicalNot(self::identicalTo('slotCounts')));
        $months = new MonthOverview(BookingCalendar::forSite($this->fixture->site), $bookings, FrozenClock::at(AccountFixture::NOW));

        $sheet = $months->month(Month::tryFrom('2026-12'));

        self::assertNotNull($sheet);
        self::assertSame(6, $sheet->booked());
        self::assertSame(1, $sheet->outOfSchedule());
        self::assertCount(31, $sheet->days);
    }

    /** La riga di un giorno del mese, dal suo link alla fine dell'elemento dell'elenco. */
    private static function day(string $html, string $date): string
    {
        self::assertSame(1, preg_match('#<a href="/gestione/\?giorno=' . $date . '" class="fs-month__day.*?</li>#s', $html, $match), 'Nessun giorno ' . $date);

        return $match[0] ?? self::fail();
    }

    private static function date(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail('Giorno non valido: ' . $value);
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? self::fail('Orario non valido: ' . $value);
    }
}
