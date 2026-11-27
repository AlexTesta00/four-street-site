<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Admin\AdminModule;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Http\AdminNotice;
use FourStreet\Admin\Http\RosterController;
use FourStreet\Admin\Http\StaffBlockedPage;
use FourStreet\Application;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\Staff\DayRoster;
use FourStreet\Booking\Staff\RosterDay;
use FourStreet\Booking\Staff\RosterSlot;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Il giorno nel pannello del titolare, attraverso l'applicazione vera: chi è prenotato in ogni fascia (posto, nome,
 * email del cliente, chi l'ha inserita), cosa si può fare e quando, il giorno senza fasce, la navigazione fra i giorni
 * e l'avviso dopo un redirect. L'orologio parte da mercoledì 25 novembre 2026, 08:00.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(AdminUrls::class)]
#[CoversClass(AdminNotice::class)]
#[CoversClass(RosterController::class)]
#[CoversClass(StaffBlockedPage::class)]
#[CoversClass(DayRoster::class)]
#[CoversClass(RosterDay::class)]
#[CoversClass(RosterSlot::class)]
#[CoversClass(PdoBookingRepository::class)]
final class AdminDayTest extends TestCase
{
    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->browser = $this->fixture->browser();
        $this->fixture->logIn($this->browser, $this->fixture->admin()->email->value);
    }

    public function testEverySlotOfTheDayWithWhoIsBookedAndWhatCanBeDone(): void
    {
        $anna = $this->fixture->customer('anna@example.test', 'Anna Bianchi');
        $luca = $this->fixture->customer('luca@example.test', 'Luca Verdi');
        $this->fixture->book($anna, '2026-12-07', '07:00');
        (new PdoBookingRepository($this->fixture->database))->place($luca->id, $this->fixture->slot('2026-12-07', '07:00'), 5, null, $this->fixture->clock->now(), CreatedBy::Admin);
        $this->fixture->bookGuest($this->fixture->user('titolare@example.test'), 'Marco', '2026-12-07', '07:00');

        $page = $this->browser->get('/gestione/?giorno=2026-12-07');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('<title>4Street · Gestione · Lunedì 7 dicembre</title>', $page->body);
        self::assertStringContainsString('Lunedì 7 dicembre.</h1>', $page->body);
        self::assertSame(6, substr_count($page->body, '<section class="fs-roster'));

        // La fascia delle 07:00: tre persone, dal posto 1, con l'email solo per chi ha l'account.
        $slot = self::slot($page->body, '0700');
        self::assertStringContainsString('<h2 id="fascia-0700" class="fs-roster__time"><span class="sr-only">Fascia delle </span>07:00<span class="sr-only">, fino alle 08:00: 3 posti occupati su 5</span></h2>', $slot);
        self::assertStringContainsString('<span class="fs-roster__count">3/5</span>', $slot);
        self::assertStringContainsString('<span class="fs-seats fs-seats--md" aria-hidden="true">', $slot);
        self::assertSame(3, substr_count($slot, 'fs-seat--taken'));
        self::assertStringContainsString('<p class="fs-roster__name"><span class="sr-only">Posto 1: </span>Anna Bianchi</p>', $slot);
        self::assertStringContainsString('<p class="fs-roster__meta">anna@example.test</p>', $slot);
        self::assertStringContainsString('<p class="fs-roster__name"><span class="sr-only">Posto 2: </span>Luca Verdi</p>', $slot);
        self::assertStringContainsString('<p class="fs-roster__meta">luca@example.test · inserita dalla palestra</p>', $slot);
        self::assertStringContainsString('<p class="fs-roster__name"><span class="sr-only">Posto 3: </span>Marco</p>', $slot);
        self::assertStringContainsString('<p class="fs-roster__meta">Senza account · inserita dalla palestra</p>', $slot);
        self::assertStringContainsString('<a href="/gestione/prenotazioni/1/disdici/" class="fs-btn fs-btn--ghost fs-btn--sm fs-roster__action" aria-label="Disdici la prenotazione di Anna Bianchi, lunedì 7 dicembre alle 07:00"><span class="fs-btn__label">Disdici</span></a>', $slot);
        self::assertSame(3, substr_count($slot, '>Disdici</span></a>'));
        self::assertStringContainsString('<a href="/gestione/prenotazioni/nuova/?giorno=2026-12-07&amp;fascia=07%3A00" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="Aggiungi una persona, lunedì 7 dicembre alle 07:00">', $slot);

        // Una fascia vuota lo dice, e si aggiunge.
        $empty = self::slot($page->body, '0930');
        self::assertStringContainsString('<p class="fs-roster__empty">Nessuno prenotato.</p>', $empty);
        self::assertStringContainsString('<span class="sr-only">, fino alle 10:30: nessuno prenotato</span>', $empty);
        self::assertStringContainsString('>Aggiungi</span>', $empty);

        // Il ritorno all'account, nessun dato personale nell'indirizzo.
        self::assertStringContainsString('<a class="fs-text-link" href="/account/">Il tuo account</a>', $page->body);
    }

    public function testAFullSlotStillCancelsButNoLongerAdds(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->fixture->book($this->fixture->customer($name . '@example.test'), '2026-12-07', '18:30');
        }

        $slot = self::slot($this->browser->get('/gestione/?giorno=2026-12-07')->body, '1830');

        self::assertStringContainsString('<span class="sr-only">, fino alle 19:30: completa, 5 su 5</span>', $slot);
        self::assertStringContainsString('<p class="fs-roster__state"><span class="fs-tag">Completa</span></p>', $slot);
        self::assertSame(5, substr_count($slot, '>Disdici</span></a>'));
        self::assertStringNotContainsString('Aggiungi', $slot);
    }

    public function testSlotsThatHaveStartedAreOnlyToBeLookedAt(): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '07:00');
        $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-07', '18:30');
        $this->fixture->clock->moveTo('2026-12-07 18:45:00');

        $page = $this->browser->get('/gestione/');
        self::assertStringContainsString('Lunedì 7 dicembre.</h1>', $page->body, 'Senza giorno: oggi.');
        self::assertStringContainsString('Oggi.</p>', $page->body);

        $over = self::slot($page->body, '0700');
        self::assertStringContainsString('<section class="fs-roster fs-roster--over" aria-labelledby="fascia-0700">', $over);
        self::assertStringContainsString('<span class="fs-tag">Finita</span>', $over);
        self::assertStringContainsString('Anna Bianchi', $over);
        self::assertStringNotContainsString('Disdici', $over);

        $now = self::slot($page->body, '1830');
        self::assertStringContainsString('<section class="fs-roster fs-roster--now" aria-labelledby="fascia-1830">', $now);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event"><span class="fs-tag__dot fs-anim-breathe"></span>In corso</span>', $now);
        self::assertStringNotContainsString('Disdici', $now);
        self::assertStringNotContainsString('Aggiungi', $now);

        self::assertStringContainsString('>Aggiungi</span>', self::slot($page->body, '2000'), 'Le 20:00 non sono ancora iniziate.');
    }

    public function testAPastDayIsOnlyToBeLookedAt(): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $this->fixture->clock->moveTo('2026-12-08 09:00:00');

        $page = $this->browser->get('/gestione/?giorno=2026-12-07');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('Giorno passato: si guarda soltanto.</p>', $page->body);
        self::assertStringContainsString('Anna Bianchi', $page->body);
        self::assertStringNotContainsString('Disdici', $page->body);
        self::assertStringNotContainsString('Aggiungi', $page->body);
    }

    public function testWithoutADayItOpensTodayOrTheFirstDayWithSlots(): void
    {
        // Prima dell'inaugurazione (e la domenica 6, il giorno dell'apertura, è chiusa): lunedì 7.
        self::assertStringContainsString('Lunedì 7 dicembre.</h1>', $this->browser->get('/gestione/')->body);

        $this->fixture->clock->moveTo('2026-12-08 21:30:00');
        self::assertStringContainsString('Martedì 8 dicembre.</h1>', $this->browser->get('/gestione/')->body, 'Oggi, anche a fasce finite.');

        $this->fixture->clock->moveTo('2026-12-13 10:00:00');
        self::assertStringContainsString('Lunedì 14 dicembre.</h1>', $this->browser->get('/gestione/')->body, 'Domenica è chiuso: il primo giorno con le fasce.');
    }

    public function testADayWithoutSlotsSaysWhy(): void
    {
        $sunday = $this->browser->get('/gestione/?giorno=2026-12-13');
        self::assertSame(200, $sunday->status);
        self::assertStringContainsString('<p class="text-body text-fg-muted">Nessuna fascia questo giorno: la palestra è chiusa.</p>', $sunday->body);
        self::assertStringNotContainsString('fs-roster', $sunday->body);

        $before = $this->browser->get('/gestione/?giorno=2026-11-30');
        self::assertStringContainsString('Nessuna fascia: si comincia dall&#039;inaugurazione, domenica 6 dicembre.', $before->body);

        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['closed_dates' => ['2026-12-08']])]);
        $browser = $fixture->browser();
        $fixture->logIn($browser, $fixture->admin()->email->value);
        self::assertStringContainsString('Nessuna fascia questo giorno: la palestra è chiusa.', $browser->get('/gestione/?giorno=2026-12-08')->body);
    }

    public function testTheDayBeforeTheDayAfterTodayAndTheQuickChoice(): void
    {
        $page = $this->browser->get('/gestione/?giorno=2026-12-07');

        self::assertStringContainsString('<nav class="fs-daynav" aria-label="Giorno prima e dopo">', $page->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-06" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step">', $page->body);
        // Il testo che si vede ("dom 6") sta nel nome del link, con il giorno per intero per i lettori di schermo.
        self::assertStringContainsString('<span><span class="sr-only">Giorno prima: </span>dom 6<span class="sr-only">, domenica 6 dicembre</span></span>', $page->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-08" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step fs-daynav__step--next">', $page->body);
        self::assertStringContainsString('<span><span class="sr-only">Giorno dopo: </span>mar 8<span class="sr-only">, martedì 8 dicembre</span></span>', $page->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-11-25" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__today">Oggi</a>', $page->body);

        // La scelta rapida: dal giorno dell'inaugurazione ai giorni prenotabili; il giorno mostrato è segnato,
        // quello senza fasce non è un link.
        self::assertStringContainsString('<details class="fs-daypicker">', $page->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-07" class="fs-day" aria-current="page">', $page->body);
        self::assertStringContainsString('<span class="sr-only">Domenica 6 dicembre, nessuna fascia</span>', $page->body);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-09" class="fs-day">', $page->body);
        self::assertStringNotContainsString('?giorno=2026-12-10" class="fs-day', $page->body, 'Oltre i giorni prenotabili, solo con il giorno dopo.');

        // Oggi: niente "Oggi"; un giorno lontano, anche di un altro anno, si apre con il giorno dopo.
        $this->fixture->clock->moveTo('2026-12-07 08:00:00');
        self::assertStringNotContainsString('fs-daynav__today', $this->browser->get('/gestione/?giorno=2026-12-07')->body);
        $far = $this->browser->get('/gestione/?giorno=2027-01-04');
        self::assertSame(200, $far->status);
        self::assertStringContainsString('Lunedì 4 gennaio 2027.</h1>', $far->body);
        self::assertStringContainsString('>Aggiungi</span>', $far->body, 'Il titolare prenota anche oltre i giorni prenotabili.');
    }

    public function testThePanelReachesTenYearsEachWayAndNoFurther(): void
    {
        // Oggi è il 25 novembre 2026: il 31 dicembre 2036 si apre, senza il giorno dopo; il 1° gennaio 2016 senza il giorno prima.
        $last = $this->browser->get('/gestione/?giorno=2036-12-31');
        self::assertSame(200, $last->status);
        self::assertStringContainsString('<span class="sr-only">Giorno prima: </span>', $last->body);
        self::assertStringNotContainsString('fs-daynav__step--next', $last->body);
        $first = $this->browser->get('/gestione/?giorno=2016-01-01');
        self::assertSame(200, $first->status);
        self::assertStringNotContainsString('<span class="sr-only">Giorno prima: </span>', $first->body);
        self::assertStringContainsString('fs-daynav__step--next', $first->body);

        // Oltre, e ai bordi del calendario, la pagina non c'è: mai un errore.
        foreach (['2037-01-01', '2015-12-31', '0000-01-01', '9999-12-31'] as $day) {
            $page = $this->browser->get('/gestione/?giorno=' . $day);
            self::assertSame(404, $page->status, $day);
            self::assertStringContainsString('Fascia non trovata.</h1>', $page->body);
        }
        self::assertSame(404, $this->browser->get('/gestione/prenotazioni/nuova/?giorno=2037-01-05&fascia=18%3A30')->status);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testAWronglyWrittenDayIsNotFound(): void
    {
        foreach (['domani', '2026-02-30', '7-12-2026', '<script>'] as $day) {
            $page = $this->browser->get('/gestione/?giorno=' . rawurlencode($day));
            self::assertSame(404, $page->status, $day);
            self::assertStringContainsString('Fascia non trovata.</h1>', $page->body);
            self::assertStringContainsString('<a href="/gestione/" class="fs-btn fs-btn--primary fs-btn--block">Torna alla gestione</a>', $page->body);
            self::assertStringNotContainsString('<script>', $page->body);
        }
    }

    public function testBookingsInASlotThatTheScheduleNoLongerHasStillShow(): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '12:30');

        // Dopo, l'orario del lunedì perde le 12:30: la prenotazione resta, e si vede.
        $week = SiteFixture::week();
        $week['lunedì'] = ['07:00', '09:30', '17:30', '18:30', '20:00'];
        $changed = new AccountFixture(site: ['booking' => SiteFixture::booking(['schedule' => $week])], database: $this->fixture->database);
        $browser = $changed->browser();
        $changed->logIn($browser, 'titolare@example.test');

        $page = $browser->get('/gestione/?giorno=2026-12-07');
        self::assertSame(6, substr_count($page->body, '<section class="fs-roster'), 'Cinque fasce, più quella che non c\'è più.');
        $slot = self::slot($page->body, '1230');
        self::assertStringContainsString('<p class="fs-roster__state"><span class="fs-tag">Fuori dall&#039;orario</span></p>', $slot);
        self::assertStringContainsString('Anna Bianchi', $slot);
        self::assertStringContainsString('>Disdici</span></a>', $slot, 'Si disdice ancora.');
        self::assertStringNotContainsString('Aggiungi', $slot, 'Ma non si aggiunge nessuno.');
        // Le fasce restano in ordine d'orario.
        self::assertLessThan(strpos($page->body, 'id="fascia-1730"'), strpos($page->body, 'id="fascia-1230"'));
    }

    public function testTheNoticeAfterARedirectReadsThePersonFromTheDatabase(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $guest = $this->fixture->bookGuest($this->fixture->user('titolare@example.test'), 'Marco <b>Neri</b>', '2026-12-07', '07:00');

        $booked = $this->browser->get('/gestione/?giorno=2026-12-07&avviso=prenotazione-inserita&prenotazione=' . $booking->id);
        self::assertStringContainsString('<p class="fs-alert__title">Prenotazione inserita</p>', $booked->body);
        self::assertStringContainsString('Anna Bianchi, lunedì 7 dicembre alle 18:30. Gli abbiamo mandato l&#039;email di conferma, e la trova nel suo account.', $booked->body);

        $forGuest = $this->browser->get('/gestione/?giorno=2026-12-07&avviso=prenotazione-inserita&prenotazione=' . $guest->id);
        self::assertStringContainsString('Marco &lt;b&gt;Neri&lt;/b&gt;, lunedì 7 dicembre alle 07:00. Non ha l&#039;account: la prenotazione la vedi solo qui.', $forGuest->body);
        self::assertStringNotContainsString('<b>Neri</b>', $forGuest->body, 'Il nome passa sempre dall\'escape.');

        // Senza una prenotazione che si trovi, l'avviso senza nomi; un avviso sconosciuto non c'è.
        self::assertStringContainsString('<p>Era già in questa fascia: non è cambiato nulla.</p>', $this->browser->get('/gestione/?avviso=prenotazione-gia-inserita&prenotazione=999')->body);
        self::assertStringNotContainsString('fs-alert', $this->browser->get('/gestione/?avviso=qualcosa&prenotazione=' . $booking->id)->body);
    }

    /** La scheda di una fascia, dal suo id ("0700") alla fine della sezione. */
    private static function slot(string $html, string $id): string
    {
        self::assertSame(1, preg_match('#<section class="fs-roster[^"]*" aria-labelledby="fascia-' . $id . '">.*?</section>#s', $html, $match), 'Nessuna fascia ' . $id);

        return $match[0] ?? self::fail();
    }
}
