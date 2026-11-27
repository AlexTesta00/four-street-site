<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Admin\AdminModule;
use FourStreet\Admin\Form\SettingsForm;
use FourStreet\Admin\Http\SettingsController;
use FourStreet\Application;
use FourStreet\Booking\Http\BookingsOpen;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\Schedule\RulesChange;
use FourStreet\Booking\Schedule\RulesEditor;
use FourStreet\Booking\Schedule\RulesOutcome;
use FourStreet\Booking\Staff\StaffThrottle;
use FourStreet\Http\Response;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le impostazioni del pannello, attraverso l'applicazione vera: il modulo parte dalle regole in vigore (quelle del file
 * finché il titolare non salva) e dice che effetto hanno; salvate, cambiano davvero cosa vede e può fare il cliente
 * (giorni prenotabili, fino a quando si prenota e si disdice, prenotazioni future, prenotazioni accese o spente);
 * l'interruttore generale del file resta di chi gestisce il sito. E le protezioni: errori sul campo, token CSRF,
 * limite dei salvataggi. L'orologio parte da mercoledì 25 novembre 2026, 08:00; l'inaugurazione è domenica 6 dicembre.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(SettingsController::class)]
#[CoversClass(SettingsForm::class)]
#[CoversClass(RulesEditor::class)]
#[CoversClass(RulesChange::class)]
#[CoversClass(RulesOutcome::class)]
#[CoversClass(PdoSchedule::class)]
#[CoversClass(BookingsOpen::class)]
#[CoversClass(StaffThrottle::class)]
final class AdminSettingsTest extends TestCase
{
    private const string PAGE = '/gestione/impostazioni/';

    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->browser = $this->fixture->browser();
        $this->fixture->logIn($this->browser, $this->fixture->admin()->email->value);
    }

    public function testTheFormStartsFromTheRulesInForceAndSaysWhatTheyDo(): void
    {
        $page = $this->browser->get(self::PAGE);

        self::assertSame(200, $page->status);
        self::assertStringContainsString('<title>4Street · Gestione · Impostazioni</title>', $page->body);
        self::assertStringContainsString('Impostazioni.</h1>', $page->body);
        self::assertStringContainsString('<a href="/gestione/impostazioni/" class="fs-adminnav__link" aria-current="page">Impostazioni</a>', $page->body);
        self::assertStringContainsString('<form method="post" action="/gestione/impostazioni/" class="fs-form" novalidate data-submit-once>', $page->body);
        self::assertStringContainsString('<input type="hidden" name="csrf" value="', $page->body);

        // Le prenotazioni dal sito: due scelte grandi, accese.
        self::assertStringContainsString('<legend class="fs-radios__legend fs-label">Prenotazioni dal sito</legend>', $page->body);
        self::assertStringContainsString('<input class="fs-radio__input" type="radio" id="prenotazioni" name="prenotazioni" value="accese" checked>', $page->body);
        self::assertStringContainsString('<input class="fs-radio__input" type="radio" id="prenotazioni-spente" name="prenotazioni" value="spente">', $page->body);
        self::assertStringContainsString('<span class="fs-radio__hint">I clienti scelgono la fascia e prenotano dal sito.</span>', $page->body);

        // Ogni campo con l'aiuto che dice l'effetto adesso, con i valori veri (prima dell'inaugurazione si prenota da lì).
        self::assertStringContainsString('<input class="fs-input fs-rules__input" id="giorni-prenotabili" name="giorni-prenotabili" type="text" value="14" inputmode="numeric" autocomplete="off" maxlength="2" aria-describedby="giorni-prenotabili-aiuto">', $page->body);
        self::assertStringContainsString('<p class="fs-field__hint" id="giorni-prenotabili-aiuto">Adesso i clienti vedono e prenotano dall&#039;inaugurazione, domenica 6 dicembre, fino a mercoledì 9 dicembre. Da 0 (solo oggi) a 62.</p>', $page->body);
        self::assertStringContainsString('<select class="fs-input fs-select__input" id="chiusura-prenotazioni" name="chiusura-prenotazioni" aria-describedby="chiusura-prenotazioni-aiuto">', $page->body);
        self::assertStringContainsString('<p class="fs-field__hint" id="chiusura-prenotazioni-aiuto">Adesso la fascia delle 18:30 si prenota fino alle 18:30, quando comincia.</p>', $page->body);
        self::assertStringContainsString('<p class="fs-field__hint" id="chiusura-disdette-aiuto">Adesso chi ha la fascia delle 18:30 la disdice dal suo account fino alle 18:30, quando comincia.</p>', $page->body);
        self::assertSame(2, substr_count($page->body, '<option value="0" selected>Fino all&#039;inizio della fascia</option>'));
        foreach (['<option value="15">Fino a 15 minuti prima</option>', '<option value="60">Fino a un&#039;ora prima</option>', '<option value="1440">Fino a 24 ore prima</option>', '<option value="2880">Fino a 48 ore prima</option>'] as $option) {
            self::assertSame(2, substr_count($page->body, $option), $option);
        }
        self::assertStringContainsString('<input class="fs-input fs-rules__input" id="prenotazioni-future" name="prenotazioni-future" type="text" value="6"', $page->body);
        self::assertStringContainsString('Adesso ogni cliente ha al massimo 6 prenotazioni in arrivo, una al giorno: quando una passa, o la disdice, ne prenota un&#039;altra. Da 1 a 62.', $page->body);

        // Un solo bottone; posti e durata della fascia restano fissi.
        self::assertSame(1, substr_count($page->body, 'type="submit"'));
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Salva</button>', $page->body);
        self::assertStringContainsString('Posti per fascia (5) e durata della fascia (60 minuti) restano fissi: li cambia chi gestisce il sito.', $page->body);
        self::assertStringNotContainsString('fs-alert', $page->body);
        self::assertSame(200, $this->browser->head(self::PAGE)->status);
    }

    public function testTheFileValuesAreTheStartingPointUntilTheOwnerSaves(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking([
            'days_ahead' => 20,
            'booking_closes_minutes_before' => 45,
            'cancel_closes_minutes_before' => 120,
            'max_upcoming' => 80,
        ])]);
        $browser = $fixture->browser();
        $fixture->logIn($browser, $fixture->admin()->email->value);

        $page = $browser->get(self::PAGE);
        self::assertStringContainsString('id="giorni-prenotabili" name="giorni-prenotabili" type="text" value="20"', $page->body);
        // Un valore del file che non è fra le scelte si aggiunge all'elenco, al suo posto.
        self::assertStringContainsString('<option value="30">Fino a 30 minuti prima</option>' . "\n" . '      <option value="45" selected>Fino a 45 minuti prima</option>' . "\n" . '      <option value="60">Fino a un&#039;ora prima</option>', $page->body);
        self::assertStringContainsString('<option value="120" selected>Fino a 2 ore prima</option>', $page->body);
        self::assertStringContainsString('Adesso la fascia delle 18:30 si prenota fino alle 17:45.', $page->body);
        self::assertStringContainsString('Adesso chi ha la fascia delle 18:30 la disdice dal suo account fino alle 16:30; dopo, la disdici tu dal pannello.', $page->body);
        // Oltre il limite del pannello, il massimo si mostra al limite.
        self::assertStringContainsString('id="prenotazioni-future" name="prenotazioni-future" type="text" value="62"', $page->body);
        self::assertSame(0, $fixture->count('booking_settings'), 'Guardare non salva nulla.');

        $saved = $browser->post(self::PAGE, self::fields(['giorni-prenotabili' => '10', 'chiusura-prenotazioni' => '45', 'chiusura-disdette' => '120', 'prenotazioni-future' => '62']));
        self::assertSame(303, $saved->status);
        self::assertStringContainsString('value="10"', $browser->get(self::PAGE)->body);

        // Salvate, valgono le regole del titolare anche se il file cambia.
        $later = new AccountFixture(site: ['booking' => SiteFixture::booking(['days_ahead' => 30])], database: $fixture->database);
        $laterBrowser = $later->browser();
        $later->logIn($laterBrowser, 'titolare@example.test');
        self::assertStringContainsString('id="giorni-prenotabili" name="giorni-prenotabili" type="text" value="10"', $laterBrowser->get(self::PAGE)->body);
    }

    public function testSavingGoesBackToThePageWithTheNotice(): void
    {
        $saved = $this->save(['giorni-prenotabili' => ' 21 ']);

        self::assertSame(303, $saved->status);
        self::assertSame('/gestione/impostazioni/?avviso=impostazioni-salvate', $saved->header('Location'));
        $page = $this->browser->follow($saved);
        self::assertStringContainsString('<div class="fs-alert fs-alert--success" role="status">', $page->body);
        self::assertStringContainsString('<p class="fs-alert__title">Impostazioni salvate</p>', $page->body);
        self::assertStringContainsString('<p>Valgono da subito per le prossime prenotazioni dei clienti; quelle già fatte restano come sono.</p>', $page->body);
        self::assertStringContainsString('value="21"', $page->body);
        self::assertStringContainsString('fino a mercoledì 16 dicembre.', $page->body, 'L\'aiuto dice già la regola nuova.');

        // Un avviso sconosciuto non c'è; nell'indirizzo nessun dato.
        self::assertStringNotContainsString('fs-alert', $this->browser->get(self::PAGE . '?avviso=prenotazione-inserita')->body);
    }

    public function testTheDaysAheadDecideHowFarCustomersBook(): void
    {
        $this->fixture->clock->moveTo('2026-12-07 08:00:00');
        $anna = $this->customerBrowser();

        // Con quattordici giorni, martedì 22 dicembre non si prenota ancora.
        self::assertStringContainsString('Il giorno che hai aperto non si prenota: ecco i prossimi.', $anna->get('/prenota/?giorno=2026-12-22')->body);
        self::assertSame(404, $anna->get('/prenota/conferma/?giorno=2026-12-22&fascia=18:30')->status);

        self::assertSame(303, $this->save(['giorni-prenotabili' => '20'])->status);

        $day = $anna->get('/prenota/?giorno=2026-12-22');
        self::assertStringNotContainsString('Il giorno che hai aperto non si prenota', $day->body);
        self::assertStringContainsString('<a href="/prenota/?giorno=2026-12-22" class="fs-day" aria-current="page">', $day->body);
        self::assertSame(200, $anna->get('/prenota/conferma/?giorno=2026-12-22&fascia=18:30')->status);
        self::assertSame(303, $anna->post('/prenota/conferma/', ['giorno' => '2026-12-22', 'fascia' => '18:30'])->status);
        self::assertSame('1', $this->fixture->seats('2026-12-22', '18:30'));
        self::assertStringContainsString('Adesso i clienti vedono e prenotano da oggi fino a domenica 27 dicembre.', $this->browser->get(self::PAGE)->body);
    }

    public function testBookingClosesTheChosenTimeBeforeTheStart(): void
    {
        $this->fixture->clock->moveTo('2026-12-07 17:45:00');
        $anna = $this->customerBrowser();
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>18:30<span class="sr-only">, 0 posti occupati su 5</span>', $anna->get('/prenota/?giorno=2026-12-07')->body);

        self::assertSame(303, $this->save(['chiusura-prenotazioni' => '60'])->status);

        // Alle 17:45 le 18:30 sono chiuse (un'ora prima); le 20:00 no.
        $day = $anna->get('/prenota/?giorno=2026-12-07');
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>18:30<span class="sr-only">, prenotazioni chiuse</span>', $day->body);
        self::assertStringContainsString('<span class="fs-slot__status fs-label text-fg-subtle" aria-hidden="true">Chiusa</span>', $day->body);
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>20:00<span class="sr-only">, 0 posti occupati su 5</span>', $day->body);
        $late = $anna->post('/prenota/conferma/', ['giorno' => '2026-12-07', 'fascia' => '18:30']);
        self::assertSame(409, $late->status);
        self::assertStringContainsString('Prenotazioni chiuse.</h1>', $late->body);
        self::assertStringContainsString('si prenota fino a un&#039;ora prima dell&#039;inizio.', $late->body);
        self::assertSame(0, $this->fixture->count('bookings'));
        self::assertStringContainsString('Adesso la fascia delle 18:30 si prenota fino alle 17:30.', $this->browser->get(self::PAGE)->body);

        // Il titolare aggiunge ancora fino all'inizio, qualunque sia la regola dei clienti.
        self::assertSame(303, $this->browser->post('/gestione/prenotazioni/nuova/', ['giorno' => '2026-12-07', 'fascia' => '18:30', 'email' => '', 'nome' => 'Ospite Prova'])->status);
    }

    public function testCancellationClosesTheChosenTimeBeforeTheStart(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $this->fixture->clock->moveTo('2026-12-07 17:45:00');
        $anna = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($anna, 'anna@example.test');
        $cancel = '/account/prenotazioni/' . $booking->id . '/disdici/';
        self::assertSame(200, $anna->get($cancel)->status);

        self::assertSame(303, $this->save(['chiusura-disdette' => '60'])->status);

        // Un'ora prima la disdetta del cliente è chiusa: non c'è niente da disdire.
        self::assertSame(404, $anna->get($cancel)->status);
        self::assertSame(404, $anna->post($cancel)->status);
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'));
        self::assertStringContainsString('<span class="fs-agenda__action fs-micro text-fg-subtle">Disdetta chiusa</span>', $anna->get('/account/')->body);
        self::assertStringContainsString('Adesso chi ha la fascia delle 18:30 la disdice dal suo account fino alle 17:30; dopo, la disdici tu dal pannello.', $this->browser->get(self::PAGE)->body);

        // Il titolare la disdice ancora, fino all'inizio.
        self::assertSame(303, $this->browser->post('/gestione/prenotazioni/' . $booking->id . '/disdici/')->status);
    }

    public function testTheMaximumOfUpcomingBookingsPerCustomer(): void
    {
        $this->fixture->clock->moveTo('2026-12-07 08:00:00');
        $customer = $this->fixture->customer('anna@example.test', 'Anna Bianchi');
        $this->fixture->book($customer, '2026-12-08', '18:30');
        $this->fixture->book($customer, '2026-12-09', '18:30');
        $anna = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($anna, 'anna@example.test');

        self::assertSame(303, $this->save(['prenotazioni-future' => '2'])->status);

        $third = $anna->post('/prenota/conferma/', ['giorno' => '2026-12-10', 'fascia' => '18:30']);
        self::assertSame(409, $third->status);
        self::assertStringContainsString('Hai già il massimo di prenotazioni.</h1>', $third->body);
        self::assertStringContainsString('Hai già 2 prenotazioni in arrivo: è il massimo.', $anna->get('/prenota/?giorno=2026-12-10')->body);
        self::assertStringContainsString('Adesso ogni cliente ha al massimo 2 prenotazioni in arrivo', $this->browser->get(self::PAGE)->body);

        self::assertSame(303, $this->save(['prenotazioni-future' => '3'])->status);
        self::assertSame(303, $anna->post('/prenota/conferma/', ['giorno' => '2026-12-10', 'fascia' => '18:30'])->status);
        self::assertSame(3, $this->fixture->count('bookings'));
    }

    public function testTheOwnerSwitchesBookingsOffAndOnAgain(): void
    {
        $anna = $this->customerBrowser();

        self::assertSame(303, $this->save(['prenotazioni' => 'spente'])->status);
        self::assertStringContainsString('<input class="fs-radio__input" type="radio" id="prenotazioni-spente" name="prenotazioni" value="spente" checked>', $this->browser->get(self::PAGE)->body);

        // Spente: le pagine per prenotare dicono "presto" (503), la landing torna alle fasce di esempio,
        // l'account non invita a prenotare; la pagina dei link non legge il database e resta com'è.
        foreach (['/prenota/', '/prenota/?giorno=2026-12-07', '/prenota/conferma/?giorno=2026-12-07&fascia=18:30'] as $uri) {
            $page = $anna->get($uri);
            self::assertSame(503, $page->status, $uri);
            self::assertStringContainsString('Prenotazioni presto.</h1>', $page->body, $uri);
        }
        self::assertSame(503, $anna->post('/prenota/conferma/', ['giorno' => '2026-12-07', 'fascia' => '18:30'])->status);
        self::assertSame(0, $this->fixture->count('bookings'));
        $home = $this->fixture->browser('198.51.100.50')->get('/');
        self::assertStringContainsString('<span class="fs-tag fs-tag--placeholder">[Fasce illustrative]</span>', $home->body);
        self::assertStringContainsString('<span id="prenota-presto" class="fs-tag">Presto</span>', $home->body);
        self::assertStringNotContainsString('Prenota una fascia', $anna->get('/account/')->body);
        self::assertStringContainsString('<a href="/prenota/" class="fs-link">', $this->fixture->browser('198.51.100.50')->get('/links/')->body);

        // Riaccese: torna tutto.
        self::assertSame(303, $this->save(['prenotazioni' => 'accese'])->status);
        self::assertSame(200, $anna->get('/prenota/')->status);
        $home = $this->fixture->browser('198.51.100.50')->get('/');
        self::assertStringNotContainsString('[Fasce illustrative]', $home->body);
        self::assertStringContainsString('<form method="get" action="/prenota/conferma/"', $home->body);
        self::assertStringContainsString('Prenota una fascia', $anna->get('/account/')->body);
        self::assertSame(303, $anna->post('/prenota/conferma/', ['giorno' => '2026-12-07', 'fascia' => '18:30'])->status);
    }

    public function testWithTheFileSwitchOffTheOwnerCannotTurnBookingsOn(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['online' => false])]);
        $browser = $fixture->browser();
        $fixture->logIn($browser, $fixture->admin()->email->value);

        $page = $browser->get(self::PAGE);
        self::assertStringNotContainsString('name="prenotazioni"', $page->body);
        self::assertStringContainsString('<p class="fs-field__label fs-label">Prenotazioni dal sito</p>', $page->body);
        self::assertStringContainsString('Spente da chi gestisce il sito, in config/site.php: da qui non si accendono.', $page->body);

        // Il campo, anche se arriva, non conta; le altre regole si salvano.
        $saved = $browser->post(self::PAGE, self::fields(['prenotazioni' => 'accese', 'giorni-prenotabili' => '20']));
        self::assertSame(303, $saved->status);
        $after = $browser->follow($saved);
        self::assertStringContainsString('value="20"', $after->body);
        self::assertStringNotContainsString('name="prenotazioni"', $after->body);
        $customer = $fixture->browser('198.51.100.30');
        self::assertSame(503, $customer->get('/prenota/')->status);

        // Quando chi gestisce il sito le riaccende nel file, tornano accese, con le regole del titolare.
        $online = new AccountFixture(database: $fixture->database);
        self::assertSame(200, $online->browser('198.51.100.30')->get('/prenota/')->status);
        $onlineBrowser = $online->browser();
        $online->logIn($onlineBrowser, 'titolare@example.test');
        $settings = $onlineBrowser->get(self::PAGE)->body;
        self::assertStringContainsString('name="prenotazioni" value="accese" checked>', $settings);
        self::assertStringContainsString('value="20"', $settings);
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function wrongForms(): iterable
    {
        yield 'giorni vuoti' => [['giorni-prenotabili' => ' '], 'giorni-prenotabili', 'Scrivi fino a quanti giorni dopo oggi si prenota: un numero da 0 a 62.'];
        yield 'giorni a parole' => [['giorni-prenotabili' => 'quattordici'], 'giorni-prenotabili', 'Scrivi un numero intero da 0 a 62, senza lettere né virgole.'];
        yield 'giorni con la virgola' => [['giorni-prenotabili' => '1,5'], 'giorni-prenotabili', 'Scrivi un numero intero da 0 a 62, senza lettere né virgole.'];
        yield 'giorni sotto zero' => [['giorni-prenotabili' => '-1'], 'giorni-prenotabili', 'Scrivi un numero intero da 0 a 62, senza lettere né virgole.'];
        yield 'giorni oltre il limite' => [['giorni-prenotabili' => '63'], 'giorni-prenotabili', 'Si prenota al massimo 62 giorni dopo oggi, circa due mesi: scrivi un numero da 0 a 62.'];
        yield 'nessuna prenotazione futura' => [['prenotazioni-future' => '0'], 'prenotazioni-future', 'Almeno una e al massimo 62 (una al giorno per circa due mesi): scrivi un numero da 1 a 62.'];
        yield 'un numero enorme' => [['prenotazioni-future' => '100000000000000000000000'], 'prenotazioni-future', 'Almeno una e al massimo 62 (una al giorno per circa due mesi): scrivi un numero da 1 a 62.'];
        yield 'prenotazioni future a parole' => [['prenotazioni-future' => 'sei'], 'prenotazioni-future', 'Scrivi un numero intero da 1 a 62, senza lettere né virgole.'];
        yield 'prenotazioni future vuote' => [['prenotazioni-future' => ''], 'prenotazioni-future', 'Scrivi quante prenotazioni future può avere un cliente: un numero da 1 a 62.'];
        yield 'scelta che non è nell\'elenco' => [['chiusura-prenotazioni' => '45'], 'chiusura-prenotazioni', 'Scegli una delle voci dell&#039;elenco: fino a quando si prenota una fascia.'];
        yield 'scelta con lo zero davanti' => [['chiusura-prenotazioni' => '060'], 'chiusura-prenotazioni', 'Scegli una delle voci dell&#039;elenco: fino a quando si prenota una fascia.'];
        yield 'scelta scritta a mano' => [['chiusura-disdette' => 'un giorno'], 'chiusura-disdette', 'Scegli una delle voci dell&#039;elenco: fino a quando si disdice una prenotazione.'];
        yield 'interruttore manomesso' => [['prenotazioni' => 'forse'], 'prenotazioni', 'Scegli se le prenotazioni dal sito sono accese o spente.'];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('wrongForms')]
    public function testAWrongFormSaysWhatToCorrectOnTheField(array $fields, string $field, string $message): void
    {
        $response = $this->save(['prenotazioni' => 'spente', ...$fields]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<title>Errore: 4Street · Gestione · Impostazioni</title>', $response->body);
        self::assertStringContainsString('<h2 id="riepilogo-errori" class="fs-alert__title">Controlla questi campi</h2>', $response->body);
        self::assertStringContainsString('<li><a href="#' . $field . '">' . $message . '</a></li>', $response->body);
        self::assertStringContainsString('<p class="fs-field__error" id="' . $field . '-errore">', $response->body);
        self::assertStringContainsString('aria-invalid="true"', $response->body);
        self::assertSame(0, $this->fixture->count('booking_settings'), 'Niente di salvato.');
        // I valori scritti tornano nel modulo.
        if ($field !== 'prenotazioni') {
            self::assertStringContainsString('name="prenotazioni" value="spente" checked>', $response->body);
        }
    }

    public function testAMissingSwitchIsAnErrorToo(): void
    {
        $response = $this->browser->post(self::PAGE, ['giorni-prenotabili' => '20', 'chiusura-prenotazioni' => '0', 'chiusura-disdette' => '0', 'prenotazioni-future' => '6']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<li><a href="#prenotazioni">Scegli se le prenotazioni dal sito sono accese o spente.</a></li>', $response->body);
        self::assertStringContainsString('<fieldset class="fs-radios" aria-describedby="prenotazioni-errore">', $response->body);
        self::assertStringContainsString('<input class="fs-radio__input" type="radio" id="prenotazioni" name="prenotazioni" value="accese" aria-invalid="true">', $response->body);
        self::assertStringContainsString('value="20"', $response->body);
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeSaving(): void
    {
        $forged = $this->browser->post(self::PAGE, self::fields(['prenotazioni' => 'spente']), ['Sec-Fetch-Site' => 'cross-site']);
        self::assertSame(403, $forged->status);
        self::assertStringContainsString('Il modulo è scaduto.</h1>', $forged->body);

        $withoutToken = $this->browser->post(self::PAGE, [...self::fields(['prenotazioni' => 'spente']), 'csrf' => str_repeat('A', 43)]);
        self::assertSame(403, $withoutToken->status);
        self::assertSame(0, $this->fixture->count('booking_settings'));
        self::assertSame(200, $this->customerBrowser()->get('/prenota/')->status);
    }

    public function testTooManySavesInAQuarterOfAnHourWait(): void
    {
        // Centocinquanta salvataggi ogni quindici minuti, con le modifiche dell'orario: largo, ferma solo un uso fuori misura.
        for ($attempt = 1; $attempt <= 150; ++$attempt) {
            self::assertSame(303, $this->save(['giorni-prenotabili' => (string) ($attempt % 60)])->status, (string) $attempt);
        }

        $throttled = $this->save(['giorni-prenotabili' => '61']);
        self::assertSame(429, $throttled->status);
        self::assertSame('900', $throttled->header('Retry-After'));
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $throttled->body);
        self::assertStringContainsString('Impostazioni.</h1>', $throttled->body, 'Lo stesso modulo, con l\'avviso.');
        self::assertStringContainsString('value="61"', $throttled->body);
        self::assertStringContainsString('value="30"', $this->browser->get(self::PAGE)->body, 'Resta l\'ultimo salvataggio.');

        $this->fixture->clock->travel('+15 minutes');
        self::assertSame(303, $this->save(['giorni-prenotabili' => '61'])->status);
    }

    /**
     * Il modulo come lo manda la pagina con le regole del file, con qualche campo cambiato.
     *
     * @param array<string, string> $changes
     *
     * @return array<string, string>
     */
    private static function fields(array $changes = []): array
    {
        return [
            'prenotazioni' => 'accese',
            'giorni-prenotabili' => '14',
            'chiusura-prenotazioni' => '0',
            'chiusura-disdette' => '0',
            'prenotazioni-future' => '6',
            ...$changes,
        ];
    }

    /**
     * @param array<string, string> $changes
     */
    private function save(array $changes = []): Response
    {
        return $this->browser->post(self::PAGE, self::fields($changes));
    }

    /** Una cliente, con l'accesso fatto in un altro browser. */
    private function customerBrowser(): TestBrowser
    {
        $this->fixture->customer('anna@example.test', 'Anna Bianchi');
        $anna = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($anna, 'anna@example.test');

        return $anna;
    }
}
