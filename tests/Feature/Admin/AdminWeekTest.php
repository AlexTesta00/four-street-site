<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Account\User;
use FourStreet\Admin\AdminModule;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\ClosureNote;
use FourStreet\Admin\Form\TimeField;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleChanges;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\ScheduleNotice;
use FourStreet\Admin\Http\StaffBlockedPage;
use FourStreet\Admin\Http\WeekController;
use FourStreet\Application;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\Schedule\ScheduleEditor;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Booking\Staff\WeekSheet;
use FourStreet\Booking\Staff\WeekSheetDay;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Una settimana precisa nel pannello del titolare, attraverso l'applicazione vera: i sette giorni con l'orario e da
 * dove viene, le persone prenotate, cosa si può fare; un giorno cambiato (un orario in più o in meno), chiuso con la
 * conferma e le disdette, riaperto, riportato alla settimana tipo; la copia nella settimana dopo. Le regole: il passato
 * non si tocca, mai fasce prima dell'inaugurazione né nell'ora che il cambio dell'ora salta. E le protezioni: campi
 * manomessi, token CSRF, limite delle modifiche. L'orologio parte da mercoledì 25 novembre 2026, 08:00;
 * l'inaugurazione è domenica 6 dicembre alle 15:00; da lunedì a sabato 07:00, 09:30, 12:30, 17:30, 18:30, 20:00,
 * la domenica chiusa; le fasce durano 60 minuti.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(AdminUrls::class)]
#[CoversClass(WeekController::class)]
#[CoversClass(StaffBlockedPage::class)]
#[CoversClass(ScheduleChanges::class)]
#[CoversClass(ScheduleNotice::class)]
#[CoversClass(ChangeReport::class)]
#[CoversClass(ScheduleDates::class)]
#[CoversClass(ChangeForm::class)]
#[CoversClass(TimeField::class)]
#[CoversClass(ClosureNote::class)]
#[CoversClass(ScheduleEditor::class)]
#[CoversClass(ScheduleBoard::class)]
#[CoversClass(WeekSheet::class)]
#[CoversClass(WeekSheetDay::class)]
#[CoversClass(PdoSchedule::class)]
final class AdminWeekTest extends TestCase
{
    private const string PAGE = '/gestione/orari/settimana/';

    private AccountFixture $fixture;

    private User $admin;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->admin = $this->fixture->admin();
        $this->browser = $this->fixture->browser();
        $this->fixture->logIn($this->browser, $this->admin->email->value);
    }

    public function testWithoutAWeekTheWeekOfTodayBeforeTheOpening(): void
    {
        $page = $this->browser->get(self::PAGE);

        self::assertSame(200, $page->status);
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('<title>4Street · Gestione · Dal 23 al 29 novembre</title>', $page->body);
        self::assertStringContainsString('Dal 23 al 29 novembre.</h1>', $page->body);
        self::assertStringContainsString('Apri un giorno per cambiarne l&#039;orario: la modifica vale solo per quel giorno.</p>', $page->body);
        // Una pagina della sezione degli orari.
        self::assertStringContainsString('<a href="/gestione/orari/" class="fs-adminnav__link" aria-current="true">Orari</a>', $page->body);
        // La settimana prima e quella dopo; "Questa settimana" no, è già questa.
        self::assertStringContainsString('<nav class="fs-daynav fs-hours__nav" aria-label="Settimana prima e dopo">', $page->body);
        self::assertStringContainsString('<a href="/gestione/orari/settimana/?da=2026-11-16" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step">', $page->body);
        self::assertStringContainsString('<span><span class="sr-only">Settimana prima: </span>16 nov<span class="sr-only">, dal 16 al 22 novembre</span></span>', $page->body);
        self::assertStringContainsString('<a href="/gestione/orari/settimana/?da=2026-11-30" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step fs-daynav__step--next">', $page->body);
        self::assertStringContainsString('<span><span class="sr-only">Settimana dopo: </span>30 nov<span class="sr-only">, dal 30 novembre al 6 dicembre</span></span>', $page->body);
        self::assertStringNotContainsString('Questa settimana', $page->body);

        // Sette giorni, tutti prima dell'inaugurazione: si guardano soltanto. Oggi è segnato.
        self::assertSame(7, substr_count($page->body, '<details class="fs-hours__fold'));
        $today = self::day($page->body, '2026-11-25');
        self::assertStringContainsString('<details class="fs-hours__fold fs-hours__fold--today fs-hours__fold--off" id="giorno-2026-11-25">', $today);
        self::assertStringContainsString('<span class="sr-only">Mercoledì 25 novembre: prima dell&#039;inaugurazione, oggi</span>', $today);
        self::assertStringContainsString('<span class="fs-hours__main">Prima dell&#039;inaugurazione</span>', $today);
        self::assertStringContainsString('<span class="fs-tag fs-tag--event">Oggi</span>', $today);
        self::assertStringContainsString('<p class="fs-hours__status">Prima dell&#039;inaugurazione: nessuna fascia.</p>', $today);
        self::assertStringContainsString('<span class="fs-tag">Passato</span>', self::day($page->body, '2026-11-23'));
        self::assertStringNotContainsString('name="azione"', $page->body, 'Niente da cambiare, nemmeno la copia: la settimana dopo comincia prima dell\'inaugurazione.');
        self::assertStringNotContainsString('Chi è prenotato', $page->body);
        self::assertStringContainsString('<p class="fs-auth__switch">Gli orari che valgono per tutte le settimane stanno nella <a class="fs-text-link" href="/gestione/orari/">settimana tipo</a>.</p>', $page->body);
        self::assertSame(200, $this->browser->head(self::PAGE)->status);
    }

    public function testEveryDaySaysWhereItsHoursComeFromAndWhoIsBooked(): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-07', '18:30');
        $this->fixture->bookGuest($this->admin, 'Marco Neri', '2026-12-07', '18:30');
        $schedule = $this->schedule();
        $schedule->saveDay(self::date('2026-12-08'), true, [], 'ferie', $this->admin->id, $this->fixture->clock->now());
        $schedule->saveDay(self::date('2026-12-09'), false, [self::time('07:00'), self::time('18:30')], null, $this->admin->id, $this->fixture->clock->now());
        // Una prenotazione arrivata proprio mentre il titolare chiudeva il giorno.
        $this->fixture->bookOutsideSchedule($this->fixture->customer('bea@example.test', 'Bea Gialli'), '2026-12-08', '18:30');

        $page = $this->browser->get(self::PAGE . '?da=2026-12-07');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('Dal 7 al 13 dicembre.</h1>', $page->body);
        self::assertStringContainsString('<a href="/gestione/orari/settimana/" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__today">Questa settimana</a>', $page->body);
        self::assertStringNotContainsString(' open>', $page->body, 'Senza "aperto" i giorni sono chiusi.');

        // Un giorno della settimana tipo, con le persone prenotate per fascia e tutto quello che si può fare.
        $monday = self::day($page->body, '2026-12-07');
        self::assertStringContainsString('<details class="fs-hours__fold" id="giorno-2026-12-07">', $monday);
        self::assertStringContainsString('<span class="sr-only">Lunedì 7 dicembre: 6 fasce, 3 prenotati, settimana tipo</span>', $monday);
        self::assertStringContainsString('<span class="fs-hours__main">6 fasce · 3 prenotati</span>', $monday);
        self::assertStringContainsString('<span class="fs-hours__meta">Settimana tipo</span>', $monday);
        self::assertStringContainsString('<span class="fs-day__weekday">lun</span>', $monday);
        self::assertStringContainsString('<span class="fs-hours__time">18:30<span class="sr-only">, 3 prenotati</span></span>' . "\n" . '        <span class="fs-hours__detail" aria-hidden="true">3 prenotati</span>', $monday);
        self::assertStringContainsString('<span class="fs-hours__time">07:00<span class="sr-only">, nessuno prenotato</span></span>' . "\n" . '      </span>', $monday);
        self::assertStringContainsString('<form method="post" action="/gestione/orari/settimana/?da=2026-12-07&amp;aperto=2026-12-07" class="fs-hours__chip-form" data-submit-once>', $monday);
        self::assertStringContainsString('<input type="hidden" name="azione" value="togli">' . "\n" . '<input type="hidden" name="giorno" value="2026-12-07">' . "\n" . '<input type="hidden" name="orario" value="18:30">', $monday);
        self::assertStringContainsString('<button type="submit" class="fs-hours__remove" aria-label="Togli le 18:30 di lunedì 7 dicembre">', $monday);
        self::assertStringContainsString('<label class="fs-field__label fs-label" for="orario-2026-12-07">Aggiungi un orario<span class="sr-only"> a lunedì 7 dicembre</span></label>', $monday);
        self::assertStringContainsString('<label class="fs-field__label fs-label" for="nota-2026-12-07">Nota della chiusura<span class="sr-only">, lunedì 7 dicembre</span></label>', $monday);
        self::assertStringContainsString('<input class="fs-input" id="nota-2026-12-07" name="nota" type="text" value="" maxlength="100" autocomplete="off" aria-describedby="nota-2026-12-07-aiuto">', $monday);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="Chiudi il giorno, lunedì 7 dicembre">Chiudi il giorno</button>', $monday);
        self::assertStringNotContainsString('Riapri', $monday);
        self::assertStringNotContainsString('Torna alla settimana tipo', $monday);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-07" class="fs-hours__link">', $monday);
        self::assertStringContainsString('<span>Chi è prenotato<span class="sr-only">, lunedì 7 dicembre</span></span>', $monday);

        // Un giorno chiuso dal titolare, con la nota (il pannello è solo suo) e la prenotazione rimasta fuori dall'orario.
        $closed = self::day($page->body, '2026-12-08');
        self::assertStringContainsString('<details class="fs-hours__fold fs-hours__fold--off" id="giorno-2026-12-08">', $closed);
        self::assertStringContainsString('<span class="sr-only">Martedì 8 dicembre: chiuso, ferie, una prenotazione fuori dall&#039;orario</span>', $closed);
        self::assertStringContainsString('<span class="fs-hours__main">Chiuso · ferie</span>', $closed);
        self::assertStringContainsString('<span class="fs-hours__marks"><span class="fs-tag fs-hours__warn">1 fuori orario</span></span>', $closed);
        self::assertStringContainsString('<p class="fs-hours__status">Chiuso tutto il giorno: ferie</p>', $closed);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="Riapri il giorno, martedì 8 dicembre" aria-describedby="giorno-2026-12-08-riapri">Riapri il giorno</button>', $closed);
        self::assertStringContainsString('<p class="fs-field__hint" id="giorno-2026-12-08-riapri">Torna alla settimana tipo: 07:00, 09:30, 12:30, 17:30, 18:30 e 20:00.</p>', $closed);
        self::assertStringContainsString('<p>Una prenotazione è in una fascia che l&#039;orario non ha più: la trovi in «Chi è prenotato», e da lì la disdici.</p>', $closed);
        self::assertStringNotContainsString('Aggiungi un orario', $closed);
        self::assertStringNotContainsString('Chiudi il giorno', $closed);

        // Un giorno con un orario suo: torna alla settimana tipo, e dice con quali orari.
        $changed = self::day($page->body, '2026-12-09');
        self::assertStringContainsString('<span class="sr-only">Mercoledì 9 dicembre: 2 fasce, nessuno prenotato, orario cambiato</span>', $changed);
        self::assertStringContainsString('<span class="fs-hours__main">2 fasce</span>', $changed);
        self::assertStringContainsString('<span class="fs-hours__meta">Orario cambiato</span>', $changed);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="Torna alla settimana tipo, mercoledì 9 dicembre" aria-describedby="giorno-2026-12-09-settimana-tipo">Torna alla settimana tipo</button>', $changed);
        self::assertStringContainsString('<p class="fs-field__hint" id="giorno-2026-12-09-settimana-tipo">07:00, 09:30, 12:30, 17:30, 18:30 e 20:00.</p>', $changed);

        // La domenica della settimana tipo: nessuna fascia, ma si aggiunge.
        $sunday = self::day($page->body, '2026-12-13');
        self::assertStringContainsString('<details class="fs-hours__fold fs-hours__fold--off" id="giorno-2026-12-13">', $sunday);
        self::assertStringContainsString('<span class="sr-only">Domenica 13 dicembre: nessuna fascia, settimana tipo</span>', $sunday);
        self::assertStringContainsString('<p class="fs-hours__status">Nessuna fascia: nella settimana tipo è chiuso.</p>', $sunday);
        self::assertStringContainsString('for="orario-2026-12-13"', $sunday);

        // In fondo la copia; mai un nome né un'email in questa pagina.
        self::assertStringContainsString('<section class="fs-hours__copy" id="copia" aria-labelledby="copia-titolo">', $page->body);
        self::assertStringContainsString('<p class="fs-hours__note">Dal 14 al 20 dicembre con gli stessi orari e le stesse chiusure di questa. Se toglie fasce con delle prenotazioni, prima ti chiede conferma.</p>', $page->body);
        self::assertStringNotContainsString('example.test', $page->body);
        self::assertStringNotContainsString('Marco Neri', $page->body);

        // "aperto" apre il suo giorno, solo se è in questa settimana.
        $open = $this->browser->get(self::PAGE . '?da=2026-12-07&aperto=2026-12-09')->body;
        self::assertStringContainsString('<details class="fs-hours__fold" id="giorno-2026-12-09" open>', $open);
        self::assertSame(1, substr_count($open, ' open>'));
        self::assertStringNotContainsString(' open>', $this->browser->get(self::PAGE . '?da=2026-12-07&aperto=2026-12-15')->body);
        self::assertStringNotContainsString(' open>', $this->browser->get(self::PAGE . '?da=2026-12-07&aperto=mercoledì')->body);
    }

    public function testADayThatIsNotAMondayGoesToItsWeek(): void
    {
        $wednesday = $this->browser->get(self::PAGE . '?da=2026-12-09&aperto=2026-12-09');
        self::assertSame(303, $wednesday->status);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-09', $wednesday->header('Location'));

        self::assertSame('/gestione/orari/settimana/?da=2026-12-07', $this->browser->get(self::PAGE . '?da=2026-12-13')->header('Location'));
        self::assertSame('/gestione/orari/settimana/?da=2026-12-28', $this->browser->get(self::PAGE . '?da=2027-01-01')->header('Location'));
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07', $this->browser->get(self::PAGE . '?da=2026-12-10&aperto=giovedì')->header('Location'));
        self::assertStringContainsString('Dal 28 dicembre 2026 al 3 gennaio 2027.</h1>', $this->browser->get(self::PAGE . '?da=2026-12-28')->body);
        self::assertStringContainsString('Dal 30 novembre al 6 dicembre.</h1>', $this->browser->get(self::PAGE . '?da=2026-11-30')->body);
        self::assertStringContainsString('Dall&#039;8 al 14 febbraio 2027.</h1>', $this->browser->get(self::PAGE . '?da=2027-02-08')->body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongWeeks(): iterable
    {
        yield 'a parole' => ['domani'];
        yield 'un giorno che non c\'è' => ['2026-02-30'];
        yield 'all\'italiana' => ['07-12-2026'];
        yield 'vuoto' => [''];
        yield 'testo qualunque' => ['<script>'];
        yield 'oltre la portata' => ['2037-01-05'];
        yield 'la settimana a cavallo dell\'ultimo anno' => ['2036-12-29'];
        yield 'prima della portata' => ['2015-12-28'];
        yield 'fine del calendario' => ['9999-12-27'];
    }

    #[DataProvider('wrongWeeks')]
    public function testAWronglyWrittenOrTooFarWeekIsNotFound(string $week): void
    {
        $page = $this->browser->get(self::PAGE . '?da=' . rawurlencode($week));

        self::assertSame(404, $page->status);
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('Fascia non trovata.</h1>', $page->body);
        self::assertStringNotContainsString('<script>', $page->body);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testThePanelReachesTenYearsEachWay(): void
    {
        $first = $this->browser->get(self::PAGE . '?da=2016-01-04');
        self::assertSame(200, $first->status);
        self::assertStringNotContainsString('Settimana prima: ', $first->body);
        self::assertStringContainsString('Settimana dopo: ', $first->body);

        $last = $this->browser->get(self::PAGE . '?da=2036-12-22');
        self::assertSame(200, $last->status);
        self::assertStringContainsString('Settimana prima: ', $last->body);
        self::assertStringNotContainsString('Settimana dopo: ', $last->body);
        self::assertStringNotContainsString('Copia questa settimana', $last->body, 'La settimana dopo esce dalla portata.');
        self::assertSame(404, $this->browser->head(self::PAGE . '?da=2037-01-05')->status);
    }

    public function testATimeAddedToOneDayAndRemovedAgain(): void
    {
        $added = $this->browser->post(self::PAGE . '?da=2026-12-07&aperto=2026-12-09', ['azione' => 'aggiungi', 'giorno' => '2026-12-09', 'orario' => '15:00']);

        self::assertSame(303, $added->status);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-09&avviso=orario-aggiunto#giorno-2026-12-09', $added->header('Location'));
        self::assertNotNull($this->fixture->calendar()->slot(self::date('2026-12-09'), self::time('15:00')));
        self::assertNull($this->fixture->calendar()->slot(self::date('2026-12-16'), self::time('15:00')), 'Solo quel giorno.');
        self::assertSame(DayKind::Changed, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind);

        // Si torna sul giorno, aperto, con l'avviso dentro.
        $page = $this->browser->follow($added);
        $day = self::day($page->body, '2026-12-09');
        self::assertStringContainsString('<details class="fs-hours__fold" id="giorno-2026-12-09" open>', $day);
        self::assertStringContainsString('<p class="fs-alert__title">Orario aggiunto</p>', $day);
        self::assertStringContainsString('<span class="fs-hours__main">7 fasce</span>', $day);
        self::assertStringContainsString('<span class="fs-hours__meta">Orario cambiato</span>', $day);
        self::assertSame(1, substr_count($page->body, '<div class="fs-alert '));

        // Tolto di nuovo, il giorno ha lo stesso orario della settimana tipo: torna a seguirla.
        $removed = $this->browser->post(self::PAGE . '?da=2026-12-07&aperto=2026-12-09', ['azione' => 'togli', 'giorno' => '2026-12-09', 'orario' => '15:00']);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-09&avviso=orario-tolto#giorno-2026-12-09', $removed->header('Location'));
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind);
        self::assertStringContainsString('<span class="fs-hours__meta">Settimana tipo</span>', self::day($this->browser->follow($removed)->body, '2026-12-09'));
    }

    public function testClosingADayWithBookingsAsksFirstThenSaysWhoToWarn(): void
    {
        $anna = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-09', '18:30');
        $luca = $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-09', '12:30');
        $marco = $this->fixture->bookGuest($this->admin, 'Marco Neri', '2026-12-09', '07:00');
        $other = $this->fixture->book($this->fixture->customer('bea@example.test', 'Bea Gialli'), '2026-12-10', '18:30');
        $fingerprint = implode(',', [$anna->id, $luca->id, $marco->id]);
        $this->fixture->mailer->unreachable = ['luca@example.test'];

        $confirm = $this->browser->post(self::PAGE . '?da=2026-12-07&aperto=2026-12-09', ['azione' => 'chiudi', 'giorno' => '2026-12-09', 'nota' => '  ferie   di Natale ']);

        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('<title>4Street · Gestione · Chiudi mercoledì 9 dicembre</title>', $confirm->body);
        self::assertStringContainsString('Chiudi mercoledì 9 dicembre?</h1>', $confirm->body);
        self::assertStringContainsString('Mercoledì 9 dicembre la palestra resta chiusa, senza fasce: le prenotazioni qui sotto si disdicono.</p>', $confirm->body);
        self::assertStringContainsString('<p class="fs-changes__note"><span class="fs-label text-fg-subtle">Nota</span> <span>ferie di Natale</span></p>', $confirm->body);
        self::assertStringContainsString('<h2 id="da-disdire" class="fs-label text-fg-subtle">3 prenotazioni da disdire</h2>', $confirm->body);
        self::assertSame(1, substr_count($confirm->body, '<div class="fs-changes__day">'));
        self::assertSame(3, substr_count($confirm->body, '<li class="fs-changes__person">'));
        // In ordine di fascia: Marco alle 07:00, Luca alle 12:30, Anna alle 18:30.
        self::assertLessThan(strpos($confirm->body, 'Luca Verdi'), strpos($confirm->body, 'Marco Neri'));
        self::assertLessThan(strpos($confirm->body, 'Anna Bianchi'), strpos($confirm->body, 'Luca Verdi'));
        self::assertStringNotContainsString('Bea Gialli', $confirm->body);
        self::assertStringContainsString('<form method="post" action="/gestione/orari/settimana/?da=2026-12-07&amp;aperto=2026-12-09" class="fs-actions" data-submit-once>', $confirm->body);
        self::assertStringContainsString('<input type="hidden" name="azione" value="chiudi">' . "\n" . '<input type="hidden" name="giorno" value="2026-12-09">' . "\n" . '<input type="hidden" name="nota" value="ferie di Natale">' . "\n" . '<input type="hidden" name="prenotazioni" value="' . $fingerprint . '">', $confirm->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Chiudi e disdici 3 prenotazioni</button>', $confirm->body);
        self::assertStringContainsString('<a href="/gestione/orari/settimana/?da=2026-12-07&amp;aperto=2026-12-09#giorno-2026-12-09" class="fs-btn fs-btn--secondary fs-btn--block">Annulla</a>', $confirm->body);
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind, 'La conferma non cambia nulla.');
        self::assertSame('111', $this->fixture->seats('2026-12-09', '07:00') . $this->fixture->seats('2026-12-09', '12:30') . $this->fixture->seats('2026-12-09', '18:30'));

        $closed = $this->browser->post(self::PAGE . '?da=2026-12-07&aperto=2026-12-09', ['azione' => 'chiudi', 'giorno' => '2026-12-09', 'nota' => 'ferie di Natale', 'prenotazioni' => $fingerprint]);

        // Nell'indirizzo solo numeri: né la nota né i nomi.
        self::assertSame(303, $closed->status);
        $location = (string) $closed->header('Location');
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-09&avviso=giorno-chiuso&disdette=3&senza-account=' . $marco->id . '&senza-email=' . $luca->id . '#giorno-2026-12-09', $location);
        self::assertStringNotContainsString('ferie', $location);
        self::assertStringNotContainsString('Neri', $location);
        $plan = $this->fixture->calendar()->plan(self::date('2026-12-09'));
        self::assertSame(DayKind::Closed, $plan->kind);
        self::assertSame('ferie di Natale', $plan->note);
        self::assertSame('', $this->fixture->seats('2026-12-09', '18:30') . $this->fixture->seats('2026-12-09', '12:30') . $this->fixture->seats('2026-12-09', '07:00'));
        self::assertSame(1, $this->fixture->database->fetchOne('SELECT active FROM bookings WHERE id = :id', ['id' => $other->id])?->nullableInt('active'));

        // L'email con il perché; quella che non parte non annulla nulla, e finisce nel log.
        $email = $this->fixture->mailer->lastTo('anna@example.test');
        self::assertSame('Prenotazione disdetta dalla palestra: mercoledì 9 dicembre, ore 18:30', $email->subject);
        self::assertStringContainsString('dalle 18:30 alle 19:30. Quel giorno la palestra resta chiusa. Ci dispiace per il cambio di programma.', $email->text);
        self::assertStringNotContainsString('ferie', $email->text, 'La nota è solo per il titolare.');
        self::assertSame([], $this->fixture->mailer->sentTo('luca@example.test'));
        self::assertCount(1, $this->fixture->reporter->reported);

        // L'avviso, nel giorno chiuso: chi avvisare a mano, per nome.
        $day = self::day($this->browser->follow($closed)->body, '2026-12-09');
        self::assertStringContainsString('<details class="fs-hours__fold fs-hours__fold--off" id="giorno-2026-12-09" open>', $day);
        self::assertStringContainsString('<p class="fs-alert__title">Giorno chiuso</p>', $day);
        self::assertStringContainsString('<p>Il giorno non ha più fasce: nessuno lo prenota.</p>', $day);
        self::assertStringContainsString('<p>3 prenotazioni disdette. Agli altri clienti registrati è arrivata l&#039;email della palestra, con il motivo.</p>', $day);
        self::assertStringContainsString('<p class="fs-changes__lead">Senza account, da avvisare tu:</p>' . "\n" . '    <ul class="fs-changes__list">' . "\n" . '      <li class="fs-changes__item"><span class="fs-changes__who">Marco Neri</span> <span class="fs-changes__when">mercoledì 9 dicembre, 07:00</span></li>', $day);
        self::assertStringContainsString('<p class="fs-changes__lead">L\'email non è partita, da avvisare tu:</p>' . "\n" . '    <ul class="fs-changes__list">' . "\n" . '      <li class="fs-changes__item"><span class="fs-changes__who">Luca Verdi</span> <span class="fs-changes__when">luca@example.test · mercoledì 9 dicembre, 12:30</span></li>', $day);
        self::assertStringNotContainsString('Anna Bianchi', $day);
        self::assertStringContainsString('<span class="fs-hours__main">Chiuso · ferie di Natale</span>', $day);

        // Il cliente quel giorno non trova più fasce.
        $customer = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($customer, 'bea@example.test');
        self::assertStringContainsString('Nessuna fascia questo giorno: scegline un altro.', $customer->get('/prenota/?giorno=2026-12-09')->body);
        self::assertSame(404, $customer->get('/prenota/conferma/?giorno=2026-12-09&fascia=18:30')->status);
    }

    public function testTheConfirmationComesBackWhenSomeoneBooksMeanwhile(): void
    {
        $anna = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-09', '18:30');
        $fields = ['azione' => 'chiudi', 'giorno' => '2026-12-09', 'nota' => ''];
        $first = $this->browser->post(self::PAGE, $fields);
        self::assertStringContainsString('<input type="hidden" name="prenotazioni" value="' . $anna->id . '">', $first->body);
        self::assertStringContainsString('Chiudi e disdici una prenotazione</button>', $first->body);
        self::assertStringContainsString('<p>Gli parte l&#039;email della palestra, con il motivo della disdetta.</p>', $first->body);
        self::assertStringNotContainsString('fs-changes__note', $first->body, 'Senza nota.');

        // Fra la pagina di conferma e il bottone, una cliente prenota quel giorno.
        $bea = $this->fixture->book($this->fixture->customer('bea@example.test', 'Bea Gialli'), '2026-12-09', '09:30');
        $again = $this->browser->post(self::PAGE, [...$fields, 'prenotazioni' => (string) $anna->id]);

        self::assertSame(200, $again->status);
        self::assertStringContainsString('<div class="fs-alert fs-alert--info" role="status">', $again->body);
        self::assertStringContainsString('<p class="fs-alert__title">Nel frattempo è cambiato qualcosa</p>', $again->body);
        self::assertStringContainsString('<p>Mentre guardavi qualcuno ha prenotato o disdetto: qui sotto c&#039;è chi è prenotato adesso. Controlla, e conferma di nuovo.</p>', $again->body);
        self::assertStringContainsString('2 prenotazioni da disdire</h2>', $again->body);
        self::assertStringContainsString('<p class="fs-changes__name">Bea Gialli</p>', $again->body);
        self::assertStringContainsString('<input type="hidden" name="prenotazioni" value="' . $anna->id . ',' . $bea->id . '">', $again->body);
        self::assertStringContainsString('Chiudi e disdici 2 prenotazioni</button>', $again->body);
        // Non è cambiato nulla: né il giorno né le prenotazioni.
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind);
        self::assertSame('1', $this->fixture->seats('2026-12-09', '18:30'));
        self::assertSame('1', $this->fixture->seats('2026-12-09', '09:30'));
        self::assertSame([], $this->fixture->mailer->sentTo('anna@example.test'));

        $closed = $this->browser->post(self::PAGE, [...$fields, 'prenotazioni' => $anna->id . ',' . $bea->id]);
        self::assertSame(303, $closed->status);
        self::assertStringContainsString('&avviso=giorno-chiuso&disdette=2#giorno-2026-12-09', (string) $closed->header('Location'));
        self::assertCount(1, $this->fixture->mailer->sentTo('anna@example.test'));
        self::assertCount(1, $this->fixture->mailer->sentTo('bea@example.test'));
    }

    public function testABookingMadeAtTheSameInstantAsAClosureStaysVisible(): void
    {
        $closed = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'giorno' => '2026-12-09', 'nota' => '']);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-09&avviso=giorno-chiuso#giorno-2026-12-09', $closed->header('Location'), 'Nessuno prenotato: niente conferma.');
        self::assertSame(DayKind::Closed, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind);
        self::assertNull($this->fixture->calendar()->plan(self::date('2026-12-09'))->note);

        // Una cliente ha prenotato proprio mentre il titolare chiudeva: la prenotazione resta, e si vede.
        $this->fixture->bookOutsideSchedule($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-09', '18:30');

        $day = self::day($this->browser->get(self::PAGE . '?da=2026-12-07')->body, '2026-12-09');
        self::assertStringContainsString('<span class="sr-only">Mercoledì 9 dicembre: chiuso, una prenotazione fuori dall&#039;orario</span>', $day);
        self::assertStringContainsString('<span class="fs-tag fs-hours__warn">1 fuori orario</span>', $day);
        self::assertStringContainsString('<p class="fs-alert__title">Da guardare</p>', $day);
        self::assertStringContainsString('<p class="fs-hours__status">Chiuso tutto il giorno.</p>', $day);
        $roster = $this->browser->get('/gestione/?giorno=2026-12-09')->body;
        self::assertStringContainsString('<span class="fs-tag">Fuori dall&#039;orario</span>', $roster);
        self::assertStringContainsString('Anna Bianchi', $roster);

        // Riaperto, le 18:30 tornano nell'orario: la prenotazione è di nuovo in una fascia vera, e nessuno è disdetto.
        $reopened = $this->browser->post(self::PAGE, ['azione' => 'riapri', 'giorno' => '2026-12-09']);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-09&avviso=giorno-riaperto#giorno-2026-12-09', $reopened->header('Location'));
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind);
        self::assertSame('1', $this->fixture->seats('2026-12-09', '18:30'));
        $after = self::day($this->browser->follow($reopened)->body, '2026-12-09');
        self::assertStringContainsString('<p class="fs-alert__title">Giorno riaperto</p>', $after);
        self::assertStringNotContainsString('fuori orario', $after);
    }

    public function testReopeningADayThatTheWeekTemplateLeavesWithoutSlotsAsksFirst(): void
    {
        // La domenica chiusa dal titolare, con una prenotazione arrivata mentre la chiudeva: riaperta, torna alla
        // settimana tipo, che la domenica non ha fasce. Quella prenotazione si disdice, ma prima il titolare la vede.
        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'giorno' => '2026-12-13', 'nota' => 'pulizie'])->status);
        $anna = $this->fixture->bookOutsideSchedule($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-13', '10:00');

        $confirm = $this->browser->post(self::PAGE, ['azione' => 'riapri', 'giorno' => '2026-12-13']);

        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('Riapri domenica 13 dicembre?</h1>', $confirm->body);
        self::assertStringContainsString('Si torna agli orari della settimana tipo, dove le fasce qui sotto non ci sono: le loro prenotazioni si disdicono.</p>', $confirm->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Riapri e disdici una prenotazione</button>', $confirm->body);
        self::assertStringNotContainsString('fs-changes__note', $confirm->body, 'Riaprire non ha una nota.');

        $reopened = $this->browser->post(self::PAGE, ['azione' => 'riapri', 'giorno' => '2026-12-13', 'prenotazioni' => (string) $anna->id]);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-13&avviso=giorno-riaperto&disdette=1#giorno-2026-12-13', $reopened->header('Location'));
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-13'))->kind);
        self::assertSame('', $this->fixture->seats('2026-12-13', '10:00'));
        self::assertStringContainsString('Quel giorno la fascia delle 10:00 non c\'è più.', $this->fixture->mailer->lastTo('anna@example.test')->text);
    }

    public function testThePastIsOnlyToBeLookedAt(): void
    {
        $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-08', '18:30');
        $this->fixture->clock->moveTo('2026-12-09 10:00:00');

        $page = $this->browser->get(self::PAGE . '?da=2026-12-07');

        // I giorni passati: si guardano, senza moduli.
        $past = self::day($page->body, '2026-12-08');
        self::assertStringContainsString('<details class="fs-hours__fold fs-hours__fold--past" id="giorno-2026-12-08">', $past);
        self::assertStringContainsString('<span class="fs-tag">Passato</span>', $past);
        self::assertStringContainsString('<p class="fs-hours__status">Passato: si guarda soltanto.</p>', $past);
        self::assertStringContainsString('<span class="fs-hours__time">18:30<span class="sr-only">, 1 prenotato</span></span>' . "\n" . '        <span class="fs-hours__detail" aria-hidden="true">1 prenotato</span>', $past);
        self::assertStringNotContainsString('<form', $past);
        self::assertStringContainsString('<a href="/gestione/?giorno=2026-12-08" class="fs-hours__link">', $past);
        // Oggi le fasce già cominciate lo dicono, e non si tolgono; il giorno non si chiude più.
        $today = self::day($page->body, '2026-12-09');
        self::assertStringContainsString('<details class="fs-hours__fold fs-hours__fold--today" id="giorno-2026-12-09">', $today);
        self::assertStringContainsString('<li class="fs-hours__chip fs-hours__chip--started">' . "\n" . '      <span class="fs-hours__chip-text">' . "\n" . '        <span class="fs-hours__time">07:00<span class="sr-only">, nessuno prenotato, cominciata</span></span>' . "\n" . '        <span class="fs-hours__detail" aria-hidden="true">Cominciata</span>', $today);
        self::assertStringNotContainsString('Togli le 07:00', $today);
        self::assertStringNotContainsString('Togli le 09:30', $today);
        self::assertStringContainsString('aria-label="Togli le 12:30 di mercoledì 9 dicembre"', $today);
        self::assertStringNotContainsString('Chiudi il giorno', $today);
        self::assertStringContainsString('for="orario-2026-12-09"', $today);

        $pastDay = $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno' => '2026-12-08', 'orario' => '18:30']);
        self::assertSame(422, $pastDay->status);
        self::assertStringContainsString('<title>Errore: 4Street · Gestione · Dal 7 al 13 dicembre</title>', $pastDay->body);
        self::assertStringContainsString('<li><a href="#giorno-2026-12-08">Martedì 8 dicembre è passato: un giorno passato non si cambia.</a></li>', $pastDay->body);
        $errorDay = self::day($pastDay->body, '2026-12-08');
        self::assertStringContainsString('<details class="fs-hours__fold fs-hours__fold--past" id="giorno-2026-12-08" open>', $errorDay);
        self::assertStringContainsString('<p class="fs-field__error" id="giorno-2026-12-08-errore">', $errorDay);
        self::assertSame('1', $this->fixture->seats('2026-12-08', '18:30'));

        $started = $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno' => '2026-12-09', 'orario' => '07:00']);
        self::assertSame(422, $started->status);
        self::assertStringContainsString('<li><a href="#giorno-2026-12-09">La fascia delle 07:00 di oggi è già cominciata: non si toglie più.</a></li>', $started->body);

        $close = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'giorno' => '2026-12-09', 'nota' => 'imprevisto']);
        self::assertSame(422, $close->status);
        self::assertStringContainsString('<li><a href="#giorno-2026-12-09">Oggi la fascia delle 07:00 è già cominciata: oggi non si chiude più. Chiudi da domani, oppure togli una per una le fasce di oggi che restano.</a></li>', $close->body);

        $late = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno' => '2026-12-09', 'orario' => '8.00']);
        self::assertSame(422, $late->status);
        self::assertStringContainsString('<li><a href="#orario-2026-12-09">Oggi le 08:00 sono già passate: si aggiungono solo fasce che devono ancora cominciare.</a></li>', $late->body);
        self::assertStringContainsString('<input class="fs-input fs-hours__input" id="orario-2026-12-09" name="orario" type="time" value="08:00" required aria-invalid="true" aria-describedby="orario-2026-12-09-errore">', $late->body);
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind, 'Niente è cambiato.');

        // Una fascia di oggi che deve ancora cominciare si toglie.
        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno' => '2026-12-09', 'orario' => '12:30'])->status);
        self::assertNull($this->fixture->calendar()->slot(self::date('2026-12-09'), self::time('12:30')));
    }

    public function testNeverASlotBeforeTheOpening(): void
    {
        $page = $this->browser->get(self::PAGE . '?da=2026-11-30');

        self::assertSame(6, substr_count($page->body, '<p class="fs-hours__status">Prima dell&#039;inaugurazione: nessuna fascia.</p>'));
        self::assertStringNotContainsString('<form', self::day($page->body, '2026-12-05'));
        // Il giorno dell'inaugurazione (domenica, chiusa nella settimana tipo) si cambia.
        self::assertStringContainsString('for="orario-2026-12-06"', self::day($page->body, '2026-12-06'));

        $before = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno' => '2026-12-05', 'orario' => '15:00']);
        self::assertSame(422, $before->status);
        self::assertStringContainsString('<li><a href="#giorno-2026-12-05">Sabato 5 dicembre è prima dell&#039;inaugurazione (domenica 6 dicembre): quel giorno non ci sono fasce.</a></li>', $before->body);

        $early = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno' => '2026-12-06', 'orario' => '10:00']);
        self::assertSame(422, $early->status);
        self::assertStringContainsString('<li><a href="#orario-2026-12-06">Le fasce cominciano dall&#039;inaugurazione, domenica 6 dicembre alle 15:00.</a></li>', $early->body);

        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno' => '2026-12-06', 'orario' => '16:00'])->status);
        self::assertNotNull($this->fixture->calendar()->slot(self::date('2026-12-06'), self::time('16:00')));
    }

    public function testNoSlotInTheHourThatTheClockChangeSkips(): void
    {
        // Domenica 28 marzo 2027 si passa all'ora legale: le 02:30 non esistono.
        $skipped = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno' => '2027-03-28', 'orario' => '02:30']);
        self::assertSame(422, $skipped->status);
        self::assertStringContainsString('<title>Errore: 4Street · Gestione · Dal 22 al 28 marzo 2027</title>', $skipped->body);
        self::assertStringContainsString('<li><a href="#orario-2027-03-28">Domenica 28 marzo 2027 le 02:30 non esistono: è il giorno del cambio dell&#039;ora.</a></li>', $skipped->body);

        // Nella settimana tipo le 02:30 della domenica si aggiungono: quel giorno il chip è spento e lo dice.
        self::assertSame(303, $this->browser->post('/gestione/orari/', ['azione' => 'aggiungi', 'giorno-settimana' => '7', 'orario' => '02:30'])->status);
        $page = $this->browser->get(self::PAGE . '?da=2027-03-22');
        self::assertStringContainsString('<li class="fs-hours__chip fs-hours__chip--missing">' . "\n" . '      <span class="fs-hours__chip-text">' . "\n" . '        <span class="fs-hours__time">02:30<span class="sr-only">, nessuno prenotato, cambio dell&#039;ora</span></span>' . "\n" . '        <span class="fs-hours__detail" aria-hidden="true">Cambio dell&#039;ora</span>', self::day($page->body, '2027-03-28'));
        self::assertStringNotContainsString('fs-hours__chip--missing', self::day($this->browser->get(self::PAGE . '?da=2027-03-15')->body, '2027-03-21'));
        self::assertNull($this->fixture->calendar()->slot(self::date('2027-03-28'), self::time('02:30')));
        self::assertNotNull($this->fixture->calendar()->slot(self::date('2027-03-21'), self::time('02:30')));
    }

    public function testADayGoesBackToTheWeekTemplate(): void
    {
        $this->schedule()->saveDay(self::date('2026-12-10'), false, [self::time('07:00'), self::time('15:00')], null, $this->admin->id, $this->fixture->clock->now());
        $anna = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-10', '15:00');
        $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-10', '07:00');

        $confirm = $this->browser->post(self::PAGE, ['azione' => 'settimana-tipo', 'giorno' => '2026-12-10']);

        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('Riporti giovedì 10 dicembre alla settimana tipo?</h1>', $confirm->body);
        self::assertStringContainsString('Giovedì 10 dicembre torna agli orari della settimana tipo (07:00, 09:30, 12:30, 17:30, 18:30 e 20:00): le altre fasce spariscono, e le prenotazioni qui sotto si disdicono.</p>', $confirm->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Riporta e disdici una prenotazione</button>', $confirm->body);
        self::assertStringNotContainsString('Luca Verdi', $confirm->body);

        $done = $this->browser->post(self::PAGE, ['azione' => 'settimana-tipo', 'giorno' => '2026-12-10', 'prenotazioni' => (string) $anna->id]);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-10&avviso=tornato-alla-settimana-tipo&disdette=1#giorno-2026-12-10', $done->header('Location'));
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-10'))->kind);
        self::assertSame('1', $this->fixture->seats('2026-12-10', '07:00'));
        self::assertStringContainsString('Quel giorno la fascia delle 15:00 non c\'è più.', $this->fixture->mailer->lastTo('anna@example.test')->text);
        $day = self::day($this->browser->follow($done)->body, '2026-12-10');
        self::assertStringContainsString('<p class="fs-alert__title">Tornato alla settimana tipo</p>', $day);
        self::assertStringContainsString('<p>Una prenotazione disdetta. Ai clienti registrati è arrivata l&#039;email della palestra, con il motivo.</p>', $day);

        // Un giorno che segue già la settimana tipo: nulla da cambiare.
        $nothing = $this->browser->post(self::PAGE, ['azione' => 'settimana-tipo', 'giorno' => '2026-12-10']);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-07&aperto=2026-12-10&avviso=nulla-da-cambiare#giorno-2026-12-10', $nothing->header('Location'));
        $info = self::day($this->browser->follow($nothing)->body, '2026-12-10');
        self::assertStringContainsString('<div class="fs-alert fs-alert--info" role="status">', $info);
        self::assertStringContainsString('<p class="fs-alert__title">Nulla da cambiare</p>', $info);

        // Riaprire un giorno che non è chiuso: lo dice sul giorno.
        $reopen = $this->browser->post(self::PAGE, ['azione' => 'riapri', 'giorno' => '2026-12-10']);
        self::assertSame(422, $reopen->status);
        self::assertStringContainsString('<li><a href="#giorno-2026-12-10">In questi giorni non c&#039;è nessuna chiusura da togliere.</a></li>', $reopen->body);
    }

    public function testTheWeekIsCopiedIntoTheNextOne(): void
    {
        $schedule = $this->schedule();
        $schedule->saveDay(self::date('2026-12-08'), true, [], 'ferie', $this->admin->id, $this->fixture->clock->now());
        $schedule->saveDay(self::date('2026-12-09'), false, [self::time('07:00'), self::time('18:30')], null, $this->admin->id, $this->fixture->clock->now());
        $anna = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-16', '12:30');
        $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-16', '18:30');

        $confirm = $this->browser->post(self::PAGE . '?da=2026-12-07', ['azione' => 'copia', 'da' => '2026-12-07']);

        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('Copia questa settimana nella prossima?</h1>', $confirm->body);
        self::assertStringContainsString('Dal 14 al 20 dicembre prende gli orari e le chiusure di questa settimana (dal 7 al 13 dicembre): le fasce che non ci sono più spariscono, e le prenotazioni qui sotto si disdicono.</p>', $confirm->body);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="giorno-da-disdire-0">Mercoledì 16 dicembre</h3>', $confirm->body);
        self::assertStringContainsString('<input type="hidden" name="azione" value="copia">' . "\n" . '<input type="hidden" name="da" value="2026-12-07">' . "\n" . '<input type="hidden" name="prenotazioni" value="' . $anna->id . '">', $confirm->body);
        self::assertStringContainsString('Copia e disdici una prenotazione</button>', $confirm->body);
        self::assertStringContainsString('<a href="/gestione/orari/settimana/?da=2026-12-07#copia" class="fs-btn fs-btn--secondary fs-btn--block">Annulla</a>', $confirm->body);

        $copied = $this->browser->post(self::PAGE . '?da=2026-12-07', ['azione' => 'copia', 'da' => '2026-12-07', 'prenotazioni' => (string) $anna->id]);

        self::assertSame(303, $copied->status);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-14&avviso=settimana-copiata&disdette=1', $copied->header('Location'));
        $calendar = $this->fixture->calendar();
        self::assertSame(DayKind::Week, $calendar->plan(self::date('2026-12-14'))->kind);
        self::assertSame(DayKind::Closed, $calendar->plan(self::date('2026-12-15'))->kind);
        self::assertSame('ferie', $calendar->plan(self::date('2026-12-15'))->note);
        self::assertSame(['07:00', '18:30'], array_map(static fn(SlotTime $time): string => $time->value, $calendar->plan(self::date('2026-12-16'))->times));
        self::assertSame('1', $this->fixture->seats('2026-12-16', '18:30'), 'Una fascia che resta tiene le sue prenotazioni.');
        // L'avviso sta in cima alla settimana dopo.
        $next = $this->browser->follow($copied)->body;
        self::assertStringContainsString('Dal 14 al 20 dicembre.</h1>', $next);
        self::assertLessThan(strpos($next, 'fs-hours__nav'), strpos($next, '<p class="fs-alert__title">Settimana copiata</p>'));

        // Di nuovo: la settimana dopo è già così.
        $again = $this->browser->post(self::PAGE, ['azione' => 'copia', 'da' => '2026-12-07']);
        self::assertSame('/gestione/orari/settimana/?da=2026-12-14&avviso=nulla-da-cambiare', $again->header('Location'));

        // Cominciata la settimana dopo, non si copia più: il bottone sparisce, e se arriva lo stesso lo dice.
        $this->fixture->clock->moveTo('2026-12-14 08:00:00');
        self::assertStringNotContainsString('Copia questa settimana', $this->browser->get(self::PAGE . '?da=2026-12-07')->body);
        $late = $this->browser->post(self::PAGE, ['azione' => 'copia', 'da' => '2026-12-07']);
        self::assertSame(422, $late->status);
        self::assertStringContainsString('<li><a href="#copia">La settimana dopo è già cominciata: si copia solo in una settimana che deve ancora cominciare.</a></li>', $late->body);
        self::assertStringContainsString('<p class="fs-field__error" id="copia-errore">', $late->body);
        self::assertStringNotContainsString('Copia questa settimana', $late->body);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function tamperedForms(): iterable
    {
        yield 'azione sconosciuta' => [['azione' => 'sposta', 'giorno' => '2026-12-09']];
        yield 'senza azione' => [['giorno' => '2026-12-09', 'orario' => '15:00']];
        yield 'senza giorno' => [['azione' => 'aggiungi', 'orario' => '15:00']];
        yield 'giorno a parole' => [['azione' => 'aggiungi', 'giorno' => '9 dicembre', 'orario' => '15:00']];
        yield 'giorno che non c\'è' => [['azione' => 'chiudi', 'giorno' => '2026-02-30']];
        yield 'giorno oltre la portata' => [['azione' => 'chiudi', 'giorno' => '2037-01-05']];
        yield 'orario di un chip scritto male' => [['azione' => 'togli', 'giorno' => '2026-12-09', 'orario' => '7:00']];
        yield 'copia di un giorno che non è lunedì' => [['azione' => 'copia', 'da' => '2026-12-09']];
        yield 'copia senza lunedì' => [['azione' => 'copia', 'da' => '']];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('tamperedForms')]
    public function testATamperedFormIsNotFoundAndChangesNothing(array $fields): void
    {
        $response = $this->browser->post(self::PAGE, $fields);

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Fascia non trovata.</h1>', $response->body);
        self::assertSame(0, $this->fixture->count('schedule_days'));
        self::assertSame(0, $this->fixture->count('schedule_weekly_slots'));
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testAWrongNoteSaysWhatToCorrectOnTheField(): void
    {
        $long = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'giorno' => '2026-12-09', 'nota' => str_repeat('ferie ', 20)]);

        self::assertSame(422, $long->status);
        self::assertStringContainsString('<li><a href="#nota-2026-12-09">La nota può avere al massimo 100 caratteri: accorciala.</a></li>', $long->body);
        $day = self::day($long->body, '2026-12-09');
        self::assertStringContainsString(' open>', $day);
        self::assertStringContainsString('aria-invalid="true" aria-describedby="nota-2026-12-09-aiuto nota-2026-12-09-errore"', $day);
        self::assertStringContainsString('value="' . str_repeat('ferie ', 20) . '"', $day);

        $control = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'giorno' => '2026-12-09', 'nota' => "ferie\u{7}"]);
        self::assertSame(422, $control->status);
        self::assertStringContainsString('La nota ha dei caratteri che non si salvano: scrivila con lettere, numeri e punteggiatura.', $control->body);
        self::assertSame(0, $this->fixture->count('schedule_days'));
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeChanging(): void
    {
        $forged = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'giorno' => '2026-12-09'], ['Sec-Fetch-Site' => 'cross-site']);

        self::assertSame(403, $forged->status);
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-09'))->kind);
    }

    public function testTooManyChangesInAQuarterOfAnHourWait(): void
    {
        for ($attempt = 1; $attempt <= 150; ++$attempt) {
            self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'settimana-tipo', 'giorno' => '2026-12-09'])->status, (string) $attempt);
        }

        $throttled = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno' => '2026-12-09', 'orario' => '15:00']);

        self::assertSame(429, $throttled->status);
        self::assertSame('900', $throttled->header('Retry-After'));
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $throttled->body);
        self::assertStringContainsString('Dal 7 al 13 dicembre.</h1>', $throttled->body);
        $day = self::day($throttled->body, '2026-12-09');
        self::assertStringContainsString(' open>', $day);
        self::assertStringContainsString('id="orario-2026-12-09" name="orario" type="time" value="15:00"', $day);
        self::assertNull($this->fixture->calendar()->slot(self::date('2026-12-09'), self::time('15:00')));
    }

    /** Il giorno della settimana, dal suo details alla fine. */
    private static function day(string $html, string $date): string
    {
        self::assertSame(1, preg_match('#<details class="fs-hours__fold[^"]*" id="giorno-' . $date . '".*?</details>#s', $html, $match), 'Nessun giorno ' . $date);

        return $match[0] ?? self::fail();
    }

    private function schedule(): PdoSchedule
    {
        return new PdoSchedule($this->fixture->database, $this->fixture->site->booking);
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
