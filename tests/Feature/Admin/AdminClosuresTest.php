<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Account\User;
use FourStreet\Admin\AdminModule;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\ClosureForm;
use FourStreet\Admin\Form\ClosureNote;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ClosuresController;
use FourStreet\Admin\Http\ScheduleChanges;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\ScheduleNotice;
use FourStreet\Application;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\Schedule\ScheduleEditor;
use FourStreet\Booking\Staff\ClosureRange;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le chiusure nel pannello del titolare, attraverso l'applicazione vera: il modulo "Chiudi la palestra" (un giorno o
 * un intervallo, con la nota per il titolare), la conferma quando ci sono prenotazioni e l'avviso dopo, l'elenco delle
 * chiusure in programma (i giorni di fila con la stessa nota in una riga, anche quelli di config/site.php) e la
 * riapertura; il cliente che quel giorno non trova più fasce. E le protezioni: date e nota scritte male (sul campo),
 * giorni che non si chiudono, campi nascosti manomessi, token CSRF, limite delle modifiche, numeri manomessi
 * nell'avviso. L'orologio parte da mercoledì 25 novembre 2026, 08:00; l'inaugurazione è domenica 6 dicembre alle 15:00.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(ClosuresController::class)]
#[CoversClass(ClosureForm::class)]
#[CoversClass(ClosureNote::class)]
#[CoversClass(ChangeForm::class)]
#[CoversClass(ScheduleChanges::class)]
#[CoversClass(ScheduleNotice::class)]
#[CoversClass(ChangeReport::class)]
#[CoversClass(ScheduleDates::class)]
#[CoversClass(ScheduleEditor::class)]
#[CoversClass(ScheduleBoard::class)]
#[CoversClass(ClosureRange::class)]
#[CoversClass(PdoSchedule::class)]
final class AdminClosuresTest extends TestCase
{
    private const string PAGE = '/gestione/chiusure/';

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

    public function testThePageWithTheFormAndNoClosures(): void
    {
        $page = $this->browser->get(self::PAGE);

        self::assertSame(200, $page->status);
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('<title>4Street · Gestione · Chiusure</title>', $page->body);
        self::assertStringContainsString('Chiusure.</h1>', $page->body);
        self::assertStringContainsString('Nei giorni chiusi non ci sono fasce e nessuno prenota. La nota la vedi solo tu.</p>', $page->body);
        self::assertStringContainsString('<a href="/gestione/chiusure/" class="fs-adminnav__link" aria-current="page">Chiusure</a>', $page->body);

        // Il modulo: il primo giorno (il calendario del telefono parte dall'inaugurazione), l'ultimo, la nota.
        self::assertStringContainsString('<h2 id="chiudi-titolo" class="fs-title-caps text-fg">Chiudi la palestra</h2>', $page->body);
        self::assertStringContainsString('<form method="post" action="/gestione/chiusure/" class="fs-form" novalidate data-submit-once>', $page->body);
        self::assertStringContainsString('<input type="hidden" name="azione" value="chiudi">', $page->body);
        self::assertStringContainsString('<label class="fs-field__label fs-label" for="da">Dal giorno</label>', $page->body);
        self::assertStringContainsString('<input class="fs-input" id="da" name="da" type="date" value="" required min="2026-12-06">', $page->body);
        self::assertStringContainsString('<p class="fs-field__hint" id="a-aiuto">Facoltativo: lascialo vuoto per chiudere un giorno solo.</p>', $page->body);
        self::assertStringContainsString('<input class="fs-input" id="a" name="a" type="date" value="" min="2026-12-06" aria-describedby="a-aiuto">', $page->body);
        self::assertStringContainsString('<p class="fs-field__hint" id="nota-aiuto">Facoltativa, per esempio: ferie. La vedi solo tu, mai i clienti.</p>', $page->body);
        self::assertStringContainsString('<input class="fs-input" id="nota" name="nota" type="text" value="" maxlength="100" autocomplete="off" aria-describedby="nota-aiuto">', $page->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Chiudi</button>', $page->body);

        // Nessuna chiusura in programma: lo stato vuoto.
        self::assertStringContainsString('<section class="fs-closures__section" id="in-programma" aria-labelledby="in-programma-titolo">', $page->body);
        self::assertStringContainsString('<p class="text-body text-fg-muted">Nessuna chiusura in programma.</p>', $page->body);
        self::assertStringNotContainsString('fs-closures__item', $page->body);
        self::assertSame(200, $this->browser->head(self::PAGE)->status);

        // Dopo l'inaugurazione il calendario parte da oggi.
        $this->fixture->clock->moveTo('2026-12-09 10:00:00');
        self::assertStringContainsString('<input class="fs-input" id="da" name="da" type="date" value="" required min="2026-12-09">', $this->browser->get(self::PAGE)->body);
    }

    public function testTheClosedDatesOfTheFileAreListedAndReopenLikeTheOthers(): void
    {
        $fixture = new AccountFixture(site: ['booking' => SiteFixture::booking(['closed_dates' => ['2026-11-20', '2026-12-08', '2026-12-24', '2026-12-25', '2027-01-01']])]);
        $browser = $fixture->browser();
        $fixture->logIn($browser, $fixture->admin()->email->value);

        $page = $browser->get(self::PAGE);

        // Da oggi in poi, in ordine; i giorni di fila in una riga; l'anno quando non è quello di oggi.
        self::assertSame(3, substr_count($page->body, '<li class="fs-closures__item">'));
        self::assertStringNotContainsString('20 novembre', $page->body, 'Una chiusura passata non c\'è.');
        self::assertStringContainsString('<p class="fs-closures__when">Martedì 8 dicembre</p>', $page->body);
        self::assertStringContainsString('<p class="fs-closures__when">Da giovedì 24 a venerdì 25 dicembre</p>' . "\n" . '        <p class="fs-closures__meta">2 giorni</p>', $page->body);
        self::assertStringContainsString('<p class="fs-closures__when">Venerdì 1 gennaio 2027</p>', $page->body);
        self::assertLessThan(strpos($page->body, 'Da giovedì 24'), strpos($page->body, 'Martedì 8 dicembre'));
        self::assertStringContainsString('<input type="hidden" name="azione" value="riapri">' . "\n" . '<input type="hidden" name="da" value="2026-12-24">' . "\n" . '<input type="hidden" name="a" value="2026-12-25">', $page->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm" aria-label="Riapri, martedì 8 dicembre">Riapri</button>', $page->body);
        self::assertStringContainsString('aria-label="Riapri, da giovedì 24 a venerdì 25 dicembre"', $page->body);

        $reopened = $browser->post(self::PAGE, ['azione' => 'riapri', 'da' => '2026-12-08', 'a' => '2026-12-08']);

        self::assertSame(303, $reopened->status);
        self::assertSame('/gestione/chiusure/?avviso=chiusura-tolta', $reopened->header('Location'));
        self::assertCount(6, $fixture->calendar()->slotsOn(self::date('2026-12-08')));
        self::assertSame(DayKind::Closed, $fixture->calendar()->plan(self::date('2026-12-24'))->kind, 'Le altre chiusure del file restano.');
        $after = $browser->follow($reopened)->body;
        self::assertStringContainsString('<p class="fs-alert__title">Chiusura tolta</p>', $after);
        self::assertStringContainsString('<p>Si torna agli orari della settimana tipo.</p>', $after);
        self::assertStringNotContainsString('Martedì 8 dicembre', $after);
        self::assertSame(2, substr_count($after, '<li class="fs-closures__item">'));
    }

    public function testClosingSomeDaysWithBookingsAsksFirstThenCustomersFindNoSlots(): void
    {
        $this->fixture->clock->moveTo('2026-12-07 08:00:00');
        $anna = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-10', '18:30');
        $marco = $this->fixture->bookGuest($this->admin, 'Marco Neri', '2026-12-11', '07:00');
        $luca = $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-12', '07:00');
        $fields = ['azione' => 'chiudi', 'da' => '2026-12-10', 'a' => '2026-12-11', 'nota' => 'ferie'];

        $confirm = $this->browser->post(self::PAGE, $fields);

        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('<title>4Street · Gestione · Chiudi da giovedì 10 a venerdì 11 dicembre</title>', $confirm->body);
        self::assertStringContainsString('Chiudi da giovedì 10 a venerdì 11 dicembre?</h1>', $confirm->body);
        self::assertStringContainsString('Dal 10 all&#039;11 dicembre la palestra resta chiusa, senza fasce: le prenotazioni qui sotto si disdicono.</p>', $confirm->body);
        self::assertStringContainsString('<a href="/gestione/chiusure/" class="fs-adminnav__link" aria-current="true">Chiusure</a>', $confirm->body);
        self::assertStringContainsString('<p class="fs-changes__note"><span class="fs-label text-fg-subtle">Nota</span> <span>ferie</span></p>', $confirm->body);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="giorno-da-disdire-0">Giovedì 10 dicembre</h3>', $confirm->body);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="giorno-da-disdire-1">Venerdì 11 dicembre</h3>', $confirm->body);
        self::assertStringNotContainsString('Luca Verdi', $confirm->body);
        self::assertStringContainsString('<form method="post" action="/gestione/chiusure/" class="fs-actions" data-submit-once>', $confirm->body);
        self::assertStringContainsString('<input type="hidden" name="azione" value="chiudi">' . "\n" . '<input type="hidden" name="da" value="2026-12-10">' . "\n" . '<input type="hidden" name="a" value="2026-12-11">' . "\n" . '<input type="hidden" name="nota" value="ferie">' . "\n" . '<input type="hidden" name="prenotazioni" value="' . $anna->id . ',' . $marco->id . '">', $confirm->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Chiudi e disdici 2 prenotazioni</button>', $confirm->body);
        self::assertStringContainsString('<a href="/gestione/chiusure/" class="fs-btn fs-btn--secondary fs-btn--block">Annulla</a>', $confirm->body);
        self::assertSame(0, $this->fixture->count('schedule_days'), 'La conferma non chiude nulla.');

        $closed = $this->browser->post(self::PAGE, [...$fields, 'prenotazioni' => $anna->id . ',' . $marco->id]);

        self::assertSame(303, $closed->status);
        self::assertSame('/gestione/chiusure/?avviso=chiusura-salvata&disdette=2&senza-account=' . $marco->id, $closed->header('Location'));
        self::assertSame('', $this->fixture->seats('2026-12-10', '18:30') . $this->fixture->seats('2026-12-11', '07:00'));
        self::assertSame('1', $this->fixture->seats('2026-12-12', '07:00'));
        self::assertSame(1, $this->fixture->database->fetchOne('SELECT active FROM bookings WHERE id = :id', ['id' => $luca->id])?->nullableInt('active'));
        self::assertStringContainsString('Quel giorno la palestra resta chiusa.', $this->fixture->mailer->lastTo('anna@example.test')->text);

        $page = $this->browser->follow($closed)->body;
        self::assertStringContainsString('<p class="fs-alert__title">Chiusura salvata</p>', $page);
        self::assertStringContainsString('<p>2 prenotazioni disdette. Ai clienti registrati è arrivata l&#039;email della palestra, con il motivo.</p>', $page);
        self::assertStringContainsString('<span class="fs-changes__who">Marco Neri</span> <span class="fs-changes__when">venerdì 11 dicembre, 07:00</span>', $page);
        self::assertStringContainsString('<p class="fs-closures__when">Da giovedì 10 a venerdì 11 dicembre</p>' . "\n" . '        <p class="fs-closures__meta">ferie · 2 giorni</p>', $page);

        // Il cliente quei giorni non trova fasce; il giorno dopo sì.
        $customer = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($customer, 'luca@example.test');
        self::assertStringContainsString('Nessuna fascia questo giorno: scegline un altro.', $customer->get('/prenota/?giorno=2026-12-10')->body);
        self::assertStringContainsString('Nessuna fascia questo giorno: scegline un altro.', $customer->get('/prenota/?giorno=2026-12-11')->body);
        self::assertStringNotContainsString('Nessuna fascia questo giorno', $customer->get('/prenota/?giorno=2026-12-12')->body);
        self::assertSame(404, $customer->get('/prenota/conferma/?giorno=2026-12-10&fascia=18:30')->status);
    }

    public function testDatesWrittenByHandAndADaySolo(): void
    {
        $single = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => ' 24/12/2026 ', 'a' => '', 'nota' => '']);
        self::assertSame(303, $single->status, 'Nessuno prenotato: niente conferma.');
        self::assertSame('/gestione/chiusure/?avviso=chiusura-salvata', $single->header('Location'));
        self::assertSame(DayKind::Closed, $this->fixture->calendar()->plan(self::date('2026-12-24'))->kind);
        self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date('2026-12-25'))->kind);

        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '1/1/2027', 'a' => '3/1/2027', 'nota' => 'Capodanno'])->status);
        $page = $this->browser->get(self::PAGE)->body;
        self::assertStringContainsString('<p class="fs-closures__when">Giovedì 24 dicembre</p>' . "\n" . '      </div>', $page);
        self::assertStringContainsString('<p class="fs-closures__when">Da venerdì 1 a domenica 3 gennaio 2027</p>' . "\n" . '        <p class="fs-closures__meta">Capodanno · 3 giorni</p>', $page);

        // Al più 62 giorni per volta: proprio 62 vanno bene.
        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '2027-06-01', 'a' => '2027-08-01', 'nota' => 'estate'])->status);
        self::assertStringContainsString('<p class="fs-closures__when">Da martedì 1 giugno a domenica 1 agosto 2027</p>' . "\n" . '        <p class="fs-closures__meta">estate · 62 giorni</p>', $this->browser->get(self::PAGE)->body);
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function wrongForms(): iterable
    {
        yield 'senza il primo giorno' => [['da' => ''], 'da', 'Scegli il primo giorno di chiusura.'];
        yield 'primo giorno a parole' => [['da' => 'domani'], 'da', 'Scrivi la data come 24/12/2026.'];
        yield 'primo giorno che non c\'è' => [['da' => '2026-02-30'], 'da', 'Questo giorno non esiste: controlla la data.'];
        yield 'primo giorno che non c\'è, a mano' => [['da' => '31/04/2027'], 'da', 'Questo giorno non esiste: controlla la data.'];
        yield 'ultimo giorno scritto male' => [['a' => '24-12-2026'], 'a', 'Scrivi la data come 24/12/2026.'];
        yield 'ultimo giorno prima del primo' => [['a' => '2026-12-23'], 'a', 'L&#039;ultimo giorno viene prima del primo: controlla le date.'];
        yield 'più di 62 giorni' => [['da' => '2027-01-01', 'a' => '2027-03-04'], 'a', 'Al massimo 62 giorni per volta: per un periodo più lungo, chiudi in due volte.'];
        yield 'nota troppo lunga' => [['nota' => str_repeat('a', 101)], 'nota', 'La nota può avere al massimo 100 caratteri: accorciala.'];
        yield 'nota con un carattere di controllo' => [['nota' => "ferie\u{1}"], 'nota', 'La nota ha dei caratteri che non si salvano: scrivila con lettere, numeri e punteggiatura.'];
        yield 'nota che non è UTF-8' => [['nota' => "ferie \xC3"], 'nota', 'La nota ha dei caratteri che non si salvano: scrivila con lettere, numeri e punteggiatura.'];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('wrongForms')]
    public function testAWrongFormSaysWhatToCorrectOnTheField(array $fields, string $field, string $message): void
    {
        $response = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '2026-12-24', 'a' => '', 'nota' => 'ferie', ...$fields]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<title>Errore: 4Street · Gestione · Chiusure</title>', $response->body);
        self::assertStringContainsString('<li><a href="#' . $field . '">' . $message . '</a></li>', $response->body);
        self::assertStringContainsString('<p class="fs-field__error" id="' . $field . '-errore">', $response->body);
        self::assertMatchesRegularExpression('#<input class="fs-input" id="' . $field . '" name="' . $field . '" type="(?:date|text)" value="[^"]*"[^>]* aria-invalid="true"#', $response->body);
        self::assertSame(0, $this->fixture->count('schedule_days'), 'Niente di salvato.');
    }

    public function testTheValuesWrittenComeBackInTheForm(): void
    {
        $response = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '24/12/2026', 'a' => '2026-12-23', 'nota' => 'ferie <b>lunghe</b>']);

        self::assertSame(422, $response->status);
        // La data scritta a mano torna come la vuole il campo data; la nota passa dall'escape.
        self::assertStringContainsString('<input class="fs-input" id="da" name="da" type="date" value="2026-12-24" required min="2026-12-06">', $response->body);
        self::assertStringContainsString('<input class="fs-input" id="a" name="a" type="date" value="2026-12-23" min="2026-12-06" aria-invalid="true" aria-describedby="a-aiuto a-errore">', $response->body);
        self::assertStringContainsString('value="ferie &lt;b&gt;lunghe&lt;/b&gt;"', $response->body);
        self::assertStringNotContainsString('<b>lunghe</b>', $response->body);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function daysThatDoNotClose(): iterable
    {
        yield 'un giorno passato' => ['2026-11-25 08:00:00', '2026-11-24', 'Martedì 24 novembre è passato: un giorno passato non si cambia.'];
        yield 'prima dell\'inaugurazione' => ['2026-11-25 08:00:00', '2026-12-01', 'Martedì 1 dicembre è prima dell&#039;inaugurazione (domenica 6 dicembre): quel giorno non ci sono fasce.'];
        yield 'oggi, con le fasce già cominciate' => ['2026-12-09 10:00:00', '2026-12-09', 'Oggi la fascia delle 07:00 è già cominciata: oggi non si chiude più. Chiudi da domani, oppure togli una per una le fasce di oggi che restano.'];
    }

    #[DataProvider('daysThatDoNotClose')]
    public function testADayThatDoesNotCloseSaysWhyOnTheFirstDay(string $now, string $from, string $message): void
    {
        $this->fixture->clock->moveTo($now);

        $response = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => $from, 'a' => '', 'nota' => '']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<li><a href="#da">' . $message . '</a></li>', $response->body);
        self::assertSame(0, $this->fixture->count('schedule_days'));
    }

    public function testDaysInARowWithTheSameNoteAreOneClosureAndReopenTogether(): void
    {
        foreach ([['2026-12-24', '2026-12-25', 'ferie'], ['2026-12-26', '', 'ferie'], ['2026-12-27', '', '']] as [$from, $to, $note]) {
            self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => $from, 'a' => $to, 'nota' => $note])->status);
        }

        $page = $this->browser->get(self::PAGE)->body;
        self::assertSame(2, substr_count($page, '<li class="fs-closures__item">'));
        self::assertStringContainsString('<p class="fs-closures__when">Da giovedì 24 a sabato 26 dicembre</p>' . "\n" . '        <p class="fs-closures__meta">ferie · 3 giorni</p>', $page);
        self::assertStringContainsString('<p class="fs-closures__when">Domenica 27 dicembre</p>' . "\n" . '      </div>', $page);

        $reopened = $this->browser->post(self::PAGE, ['azione' => 'riapri', 'da' => '2026-12-24', 'a' => '2026-12-26']);
        self::assertSame('/gestione/chiusure/?avviso=chiusura-tolta', $reopened->header('Location'));
        foreach (['2026-12-24', '2026-12-25', '2026-12-26'] as $day) {
            self::assertSame(DayKind::Week, $this->fixture->calendar()->plan(self::date($day))->kind, $day);
        }
        self::assertSame(DayKind::Closed, $this->fixture->calendar()->plan(self::date('2026-12-27'))->kind);
    }

    public function testAClosureLongerThanWhatReopensAtOnceReopensInParts(): void
    {
        // Due chiusure di fila con la stessa nota, 62 giorni e poi 7: nell'elenco sono una sola, di 69 giorni.
        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '2027-01-01', 'a' => '2027-03-03', 'nota' => 'lavori'])->status);
        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '2027-03-04', 'a' => '2027-03-10', 'nota' => 'lavori'])->status);
        $page = $this->browser->get(self::PAGE)->body;
        self::assertStringContainsString('<p class="fs-closures__when">Da venerdì 1 gennaio a mercoledì 10 marzo 2027</p>' . "\n" . '        <p class="fs-closures__meta">lavori · 69 giorni</p>', $page);

        $reopened = $this->browser->post(self::PAGE, ['azione' => 'riapri', 'da' => '2027-01-01', 'a' => '2027-03-10']);

        self::assertSame('/gestione/chiusure/?avviso=chiusura-tolta', $reopened->header('Location'));
        self::assertSame(0, $this->fixture->count('schedule_days'));
        self::assertStringContainsString('Nessuna chiusura in programma.', $this->browser->follow($reopened)->body);
    }

    public function testReopeningWhatIsNoLongerClosedSaysSo(): void
    {
        $response = $this->browser->post(self::PAGE, ['azione' => 'riapri', 'da' => '2026-12-24', 'a' => '2026-12-24']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<li><a href="#in-programma">In questi giorni non c&#039;è nessuna chiusura da togliere.</a></li>', $response->body);
        self::assertStringContainsString('<p class="fs-field__error" id="in-programma-errore">', $response->body);
        self::assertSame(0, $this->fixture->count('schedule_days'));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function tamperedForms(): iterable
    {
        yield 'azione sconosciuta' => [['azione' => 'sposta', 'da' => '2026-12-24']];
        yield 'senza azione' => [['da' => '2026-12-24', 'a' => '2026-12-24']];
        yield 'azione di un\'altra pagina' => [['azione' => 'aggiungi', 'giorno' => '2026-12-24', 'orario' => '18:30']];
        yield 'riapertura con la data scritta a mano' => [['azione' => 'riapri', 'da' => '24/12/2026', 'a' => '2026-12-24']];
        yield 'riapertura senza l\'ultimo giorno' => [['azione' => 'riapri', 'da' => '2026-12-24']];
        yield 'riapertura al contrario' => [['azione' => 'riapri', 'da' => '2026-12-25', 'a' => '2026-12-24']];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('tamperedForms')]
    public function testATamperedFormIsNotFoundAndChangesNothing(array $fields): void
    {
        $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '2026-12-24', 'a' => '', 'nota' => '']);

        $response = $this->browser->post(self::PAGE, $fields);

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Chiusure.</h1>', $response->body);
        self::assertStringContainsString('<p class="fs-alert__title">Modifica non valida</p>', $response->body);
        self::assertSame(DayKind::Closed, $this->fixture->calendar()->plan(self::date('2026-12-24'))->kind);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeClosing(): void
    {
        $forged = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '2026-12-24', 'a' => '', 'nota' => ''], ['Sec-Fetch-Site' => 'cross-site']);

        self::assertSame(403, $forged->status);
        self::assertSame(0, $this->fixture->count('schedule_days'));
    }

    public function testTooManyChangesInAQuarterOfAnHourWait(): void
    {
        for ($attempt = 1; $attempt <= 150; ++$attempt) {
            self::assertSame(422, $this->browser->post(self::PAGE, ['azione' => 'riapri', 'da' => '2026-12-24', 'a' => '2026-12-24'])->status, (string) $attempt);
        }

        $throttled = $this->browser->post(self::PAGE, ['azione' => 'chiudi', 'da' => '2026-12-24', 'a' => '', 'nota' => 'Natale']);

        self::assertSame(429, $throttled->status);
        self::assertSame('900', $throttled->header('Retry-After'));
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $throttled->body);
        self::assertStringContainsString('Chiusure.</h1>', $throttled->body);
        self::assertStringContainsString('id="nota" name="nota" type="text" value="Natale"', $throttled->body);
        self::assertSame(0, $this->fixture->count('schedule_days'));
    }

    public function testTheNoticeShowsOnlyBookingsTheGymCancelledEachInItsList(): void
    {
        $active = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $customer = $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-08', '18:30');
        $guest = $this->fixture->bookGuest($this->admin, 'Marco Neri', '2026-12-09', '07:00');
        foreach ([$customer->id, $guest->id] as $id) {
            $this->fixture->database->execute(
                "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:30:00', cancelled_by = 'admin' WHERE id = :id",
                ['id' => $id],
            );
        }

        // Ognuno nel suo elenco: un cliente registrato non è "senza account", una persona senza account non ha un'email.
        $swapped = $this->browser->get(self::PAGE . '?avviso=chiusura-salvata&disdette=3&senza-account=' . $active->id . ',' . $customer->id . '&senza-email=' . $guest->id);
        self::assertSame(200, $swapped->status);
        self::assertStringContainsString('<p class="fs-alert__title">Chiusura salvata</p>', $swapped->body);
        self::assertStringContainsString('<p>3 prenotazioni disdette. Ai clienti registrati è arrivata l&#039;email della palestra, con il motivo.</p>', $swapped->body);
        foreach (['Anna Bianchi', 'Luca Verdi', 'Marco Neri'] as $name) {
            self::assertStringNotContainsString($name, $swapped->body);
        }

        $right = $this->browser->get(self::PAGE . '?avviso=chiusura-salvata&disdette=5&senza-account=' . $guest->id . '&senza-email=' . $customer->id);
        self::assertStringContainsString('<p>5 prenotazioni disdette. Agli altri clienti registrati è arrivata l&#039;email della palestra, con il motivo.</p>', $right->body);
        self::assertStringContainsString('<span class="fs-changes__who">Marco Neri</span> <span class="fs-changes__when">mercoledì 9 dicembre, 07:00</span>', $right->body);
        self::assertStringContainsString('<span class="fs-changes__who">Luca Verdi</span> <span class="fs-changes__when">luca@example.test · martedì 8 dicembre, 18:30</span>', $right->body);

        // Cinquanta numeri in un elenco: forse non c'è tutto, e l'avviso lo dice.
        $many = implode(',', range(1000, 1049));
        $partial = $this->browser->get(self::PAGE . '?avviso=chiusura-salvata&disdette=80&senza-email=' . $many)->body;
        self::assertStringContainsString('<p>80 prenotazioni disdette.</p>', $partial);
        self::assertStringContainsString('<p>Gli elenchi arrivano al massimo a 50 persone: potrebbero essercene altre da avvisare.</p>', $partial);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    private static function date(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail('Giorno non valido: ' . $value);
    }
}
