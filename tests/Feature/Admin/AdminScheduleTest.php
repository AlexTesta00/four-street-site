<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Admin\AdminModule;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\TimeField;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleChanges;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\ScheduleNotice;
use FourStreet\Admin\Http\WeekTemplateController;
use FourStreet\Application;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\PdoSchedule;
use FourStreet\Booking\Schedule\ScheduleEditor;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Booking\Weekday;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La settimana tipo nel pannello del titolare, attraverso l'applicazione vera: le sette schede con gli orari, aggiungere
 * e togliere un orario (vale da domani, oggi resta com'è, un giorno con un orario suo non cambia), la conferma quando
 * un orario tolto ha delle prenotazioni e l'avviso dopo, con chi va avvisato a mano. E le protezioni: l'orario scritto
 * male o che non si può aggiungere (sul campo), i campi nascosti manomessi, il token CSRF, il limite delle modifiche.
 * L'orologio parte da mercoledì 25 novembre 2026, 08:00; l'inaugurazione è domenica 6 dicembre alle 15:00; da lunedì
 * a sabato 07:00, 09:30, 12:30, 17:30, 18:30, 20:00, la domenica chiusa; le fasce durano 60 minuti.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(WeekTemplateController::class)]
#[CoversClass(ScheduleChanges::class)]
#[CoversClass(ScheduleNotice::class)]
#[CoversClass(ChangeReport::class)]
#[CoversClass(ScheduleDates::class)]
#[CoversClass(ChangeForm::class)]
#[CoversClass(TimeField::class)]
#[CoversClass(ScheduleEditor::class)]
#[CoversClass(ScheduleBoard::class)]
#[CoversClass(PdoSchedule::class)]
final class AdminScheduleTest extends TestCase
{
    private const string PAGE = '/gestione/orari/';

    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->browser = $this->fixture->browser();
        $this->fixture->logIn($this->browser, $this->fixture->admin()->email->value);
    }

    public function testTheWeekTemplateShowsEveryDayWithItsTimes(): void
    {
        $page = $this->browser->get(self::PAGE);

        self::assertSame(200, $page->status);
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('<title>4Street · Gestione · Settimana tipo</title>', $page->body);
        self::assertStringContainsString('Settimana tipo.</h1>', $page->body);
        self::assertStringContainsString('<a href="/gestione/orari/" class="fs-adminnav__link" aria-current="page">Orari</a>', $page->body);
        // Come funziona: da domani, con la data; la durata delle fasce; una settimana precisa.
        self::assertStringContainsString('<p>Una modifica vale da domani, giovedì 26 novembre: oggi resta com&#039;è. Ogni fascia dura 60 minuti.</p>', $page->body);
        self::assertStringContainsString('<a href="/gestione/orari/settimana/" class="fs-btn fs-btn--secondary fs-btn--block">', $page->body);
        self::assertStringContainsString('<span>Cambia una settimana precisa</span>', $page->body);

        // Sette schede, da lunedì a domenica, con gli id fissi.
        $previous = -1;
        foreach (Weekday::cases() as $day) {
            $position = strpos($page->body, '<section class="fs-hours__day" id="giorno-' . $day->value . '" aria-labelledby="giorno-' . $day->value . '-nome">');
            self::assertIsInt($position, $day->label());
            self::assertGreaterThan($previous, $position);
            $previous = $position;
        }

        // Il lunedì: sei orari, ognuno con il suo bottone che lo toglie, e il campo per aggiungerne uno.
        $monday = self::card($page->body, 1);
        self::assertStringContainsString('<h2 id="giorno-1-nome" class="fs-hours__name">Lunedì</h2>', $monday);
        self::assertStringContainsString('<p class="fs-hours__total">6 fasce</p>', $monday);
        self::assertStringContainsString('<ul class="fs-hours__times" aria-label="Orari del lunedì">', $monday);
        self::assertSame(6, substr_count($monday, '<li class="fs-hours__chip">'));
        self::assertStringContainsString('<span class="fs-hours__time">18:30</span>', $monday);
        self::assertStringContainsString('<form method="post" action="/gestione/orari/" class="fs-hours__chip-form" data-submit-once>', $monday);
        self::assertStringContainsString('<input type="hidden" name="azione" value="togli">' . "\n" . '<input type="hidden" name="giorno-settimana" value="1">' . "\n" . '<input type="hidden" name="orario" value="18:30">', $monday);
        self::assertStringContainsString('<button type="submit" class="fs-hours__remove" aria-label="Togli le 18:30 dal lunedì">', $monday);
        self::assertStringContainsString('<form method="post" action="/gestione/orari/" class="fs-hours__add" novalidate data-submit-once>', $monday);
        self::assertStringContainsString('<input type="hidden" name="azione" value="aggiungi">' . "\n" . '<input type="hidden" name="giorno-settimana" value="1">', $monday);
        self::assertStringContainsString('<label class="fs-field__label fs-label" for="orario-1">Aggiungi un orario<span class="sr-only"> al lunedì</span></label>', $monday);
        self::assertStringContainsString('<input class="fs-input fs-hours__input" id="orario-1" name="orario" type="time" value="" required>', $monday);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--secondary fs-hours__add-button" aria-label="Aggiungi l&#039;orario al lunedì">', $monday);

        // La domenica è chiusa: nessun orario da togliere, e si aggiunge.
        $sunday = self::card($page->body, 7);
        self::assertStringContainsString('<h2 id="giorno-7-nome" class="fs-hours__name">Domenica</h2>', $sunday);
        self::assertStringContainsString('<p class="fs-hours__status">Chiuso: nessuna fascia.</p>', $sunday);
        self::assertStringNotContainsString('fs-hours__total', $sunday);
        self::assertStringNotContainsString('fs-hours__remove', $sunday);
        self::assertStringContainsString('aria-label="Aggiungi l&#039;orario alla domenica"', $sunday);

        self::assertStringNotContainsString('<div class="fs-alert', $page->body);
        self::assertSame(200, $this->browser->head(self::PAGE)->status);
    }

    public function testAnAddedTimeHoldsFromTomorrowOnTheDaysThatFollowTheTemplate(): void
    {
        // Lunedì 7 dicembre: oggi è un lunedì; lunedì 21 ha un orario suo.
        $this->fixture->clock->moveTo('2026-12-07 08:00:00');
        $this->schedule()->saveDay(self::date('2026-12-21'), false, [self::time('07:00'), self::time('18:30')], null, $this->fixture->user('titolare@example.test')->id, $this->fixture->clock->now());

        $added = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno-settimana' => '1', 'orario' => '15:00']);

        self::assertSame(303, $added->status);
        self::assertSame('/gestione/orari/?giorno-settimana=1&avviso=orario-aggiunto#giorno-1', $added->header('Location'));
        $calendar = $this->fixture->calendar();
        self::assertNull($calendar->slot(self::date('2026-12-07'), self::time('15:00')), 'Oggi resta com\'è.');
        self::assertNotNull($calendar->slot(self::date('2026-12-14'), self::time('15:00')), 'Da domani nei lunedì della settimana tipo.');
        self::assertNotNull($calendar->slot(self::date('2027-01-04'), self::time('15:00')));
        self::assertNull($calendar->slot(self::date('2026-12-21'), self::time('15:00')), 'Un giorno con un orario suo non cambia.');
        self::assertNull($calendar->slot(self::date('2026-12-15'), self::time('15:00')), 'Solo il lunedì.');

        // L'avviso sta nella scheda del giorno, dove porta il frammento.
        $page = $this->browser->follow($added);
        $monday = self::card($page->body, 1);
        self::assertStringContainsString('<p class="fs-hours__total">7 fasce</p>', $monday);
        self::assertStringContainsString('aria-label="Togli le 15:00 dal lunedì"', $monday);
        self::assertStringContainsString('<div class="fs-alert fs-alert--success" role="status">', $monday);
        self::assertStringContainsString('<p class="fs-alert__title">Orario aggiunto</p>', $monday);
        self::assertStringContainsString('<p>I clienti trovano la fascia nuova fra quelle da prenotare.</p>', $monday);
        self::assertSame(1, substr_count($page->body, '<div class="fs-alert '));

        // Il cliente la trova già fra le fasce di lunedì 14.
        $this->fixture->customer('anna@example.test', 'Anna Bianchi');
        $anna = $this->fixture->browser('198.51.100.30');
        $this->fixture->logIn($anna, 'anna@example.test');
        self::assertStringContainsString('<span class="sr-only">Fascia delle </span>15:00<span class="sr-only">, 0 posti occupati su 5</span>', $anna->get('/prenota/?giorno=2026-12-14')->body);
        self::assertStringNotContainsString('<span class="sr-only">Fascia delle </span>15:00', $anna->get('/prenota/?giorno=2026-12-07')->body);
    }

    public function testRemovingATimeWithBookingsAsksFirstThenCancelsThemAndSaysWhoToWarn(): void
    {
        $this->fixture->clock->moveTo('2026-12-07 08:00:00');
        $admin = $this->fixture->user('titolare@example.test');
        $anna = $this->fixture->customer('anna@example.test', 'Anna Bianchi');
        $luca = $this->fixture->customer('luca@example.test', 'Luca Verdi');
        $this->schedule()->saveDay(self::date('2026-12-28'), false, [self::time('18:30')], null, $admin->id, $this->fixture->clock->now());
        $today = $this->fixture->book($anna, '2026-12-07', '18:30');
        $next = $this->fixture->book($anna, '2026-12-14', '18:30');
        $guest = $this->fixture->bookGuest($admin, 'Marco Neri', '2026-12-21', '18:30');
        $this->fixture->book($luca, '2026-12-14', '07:00');
        $this->fixture->book($luca, '2026-12-28', '18:30');
        $fingerprint = $next->id . ',' . $guest->id;

        $confirm = $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno-settimana' => '1', 'orario' => '18:30']);

        // Prima la conferma, come risposta al POST: chi è prenotato dove la fascia sparisce, da domani.
        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('<title>4Street · Gestione · Togli le 18:30 dal lunedì</title>', $confirm->body);
        self::assertStringContainsString('Togli le 18:30 dal lunedì?</h1>', $confirm->body);
        self::assertStringContainsString('Da domani, martedì 8 dicembre, nei lunedì che seguono la settimana tipo la fascia delle 18:30 non c&#039;è più: le prenotazioni qui sotto si disdicono.</p>', $confirm->body);
        self::assertStringContainsString('<a href="/gestione/orari/" class="fs-adminnav__link" aria-current="true">Orari</a>', $confirm->body);
        self::assertStringContainsString('<h2 id="da-disdire" class="fs-label text-fg-subtle">2 prenotazioni da disdire</h2>', $confirm->body);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="giorno-da-disdire-0">Lunedì 14 dicembre</h3>', $confirm->body);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="giorno-da-disdire-1">Lunedì 21 dicembre</h3>', $confirm->body);
        self::assertStringContainsString('<span class="fs-changes__time"><span class="sr-only">Alle </span>18:30</span>' . "\n" . '        <p class="fs-changes__name">Anna Bianchi</p>' . "\n" . '        <p class="fs-changes__meta">anna@example.test</p>', $confirm->body);
        self::assertStringContainsString('<p class="fs-changes__name">Marco Neri</p>' . "\n" . '        <p class="fs-changes__meta">Senza account</p>', $confirm->body);
        self::assertStringNotContainsString('Luca Verdi', $confirm->body, 'Le altre fasce e i giorni con un orario loro non c\'entrano.');
        self::assertStringContainsString('<p>Ai clienti registrati parte l&#039;email della palestra, con il motivo della disdetta. Chi non ha l&#039;account lo avvisi tu.</p>', $confirm->body);
        self::assertStringContainsString('<form method="post" action="/gestione/orari/" class="fs-actions" data-submit-once>', $confirm->body);
        self::assertStringContainsString('<input type="hidden" name="azione" value="togli">' . "\n" . '<input type="hidden" name="giorno-settimana" value="1">' . "\n" . '<input type="hidden" name="orario" value="18:30">' . "\n" . '<input type="hidden" name="prenotazioni" value="' . $fingerprint . '">', $confirm->body);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Togli e disdici 2 prenotazioni</button>', $confirm->body);
        self::assertStringContainsString('<a href="/gestione/orari/#giorno-1" class="fs-btn fs-btn--secondary fs-btn--block">Annulla</a>', $confirm->body);
        self::assertStringNotContainsString('Nel frattempo', $confirm->body);
        // La conferma non cambia nulla.
        self::assertNotNull($this->fixture->calendar()->slot(self::date('2026-12-14'), self::time('18:30')));
        self::assertSame('1', $this->fixture->seats('2026-12-14', '18:30'));
        self::assertSame([], $this->fixture->mailer->sent);

        $done = $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno-settimana' => '1', 'orario' => '18:30', 'prenotazioni' => $fingerprint]);

        self::assertSame(303, $done->status);
        self::assertSame('/gestione/orari/?giorno-settimana=1&avviso=orario-tolto&disdette=2&senza-account=' . $guest->id . '#giorno-1', $done->header('Location'));
        self::assertNull($this->fixture->calendar()->slot(self::date('2026-12-14'), self::time('18:30')));
        self::assertSame('', $this->fixture->seats('2026-12-14', '18:30'));
        self::assertSame('', $this->fixture->seats('2026-12-21', '18:30'));
        self::assertSame('admin', $this->fixture->database->fetchOne('SELECT cancelled_by FROM bookings WHERE id = :id', ['id' => $next->id])?->nullableString('cancelled_by'));
        // Oggi resta com'è; le altre fasce e il giorno con un orario suo pure.
        self::assertSame('1', $this->fixture->seats('2026-12-07', '18:30'));
        self::assertSame(1, $this->fixture->database->fetchOne('SELECT active FROM bookings WHERE id = :id', ['id' => $today->id])?->nullableInt('active'));
        self::assertSame('1', $this->fixture->seats('2026-12-14', '07:00'));
        self::assertSame('1', $this->fixture->seats('2026-12-28', '18:30'));

        // Al cliente registrato l'email della palestra, con il perché; a chi non ha l'account niente.
        $email = $this->fixture->mailer->lastTo('anna@example.test');
        self::assertSame('Prenotazione disdetta dalla palestra: lunedì 14 dicembre, ore 18:30', $email->subject);
        self::assertStringContainsString('la palestra ha disdetto la tua prenotazione di lunedì 14 dicembre 2026, dalle 18:30 alle 19:30. Quel giorno la fascia delle 18:30 non c\'è più. Ci dispiace per il cambio di programma.', $email->text);
        self::assertCount(1, $this->fixture->mailer->sent);
        self::assertSame([], $this->fixture->mailer->sentTo('luca@example.test'));

        // L'avviso: quante disdette, chi ha avuto l'email e, per nome, chi va avvisato a mano.
        $monday = self::card($this->browser->follow($done)->body, 1);
        self::assertStringContainsString('<p class="fs-alert__title">Orario tolto</p>', $monday);
        self::assertStringContainsString('<p>2 prenotazioni disdette. Ai clienti registrati è arrivata l&#039;email della palestra, con il motivo.</p>', $monday);
        self::assertStringContainsString('<p class="fs-changes__lead">Senza account, da avvisare tu:</p>', $monday);
        self::assertStringContainsString('<li class="fs-changes__item"><span class="fs-changes__who">Marco Neri</span> <span class="fs-changes__when">lunedì 21 dicembre, 18:30</span></li>', $monday);
        self::assertStringNotContainsString('Anna Bianchi', $monday);
        self::assertStringNotContainsString('aria-label="Togli le 18:30 dal lunedì"', $monday);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testRemovingATimeWithoutBookingsNeedsNoConfirmation(): void
    {
        $removed = $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno-settimana' => '6', 'orario' => '07:00']);

        self::assertSame(303, $removed->status);
        self::assertSame('/gestione/orari/?giorno-settimana=6&avviso=orario-tolto#giorno-6', $removed->header('Location'));
        $saturday = self::card($this->browser->follow($removed)->body, 6);
        self::assertStringContainsString('<p class="fs-alert__title">Orario tolto</p>', $saturday);
        self::assertStringContainsString('<p>La fascia non c&#039;è più: i clienti non la trovano fra quelle da prenotare.</p>', $saturday);
        self::assertStringNotContainsString('disdette', $saturday);
        self::assertStringNotContainsString('Togli le 07:00 dal sabato', $saturday);
        self::assertNull($this->fixture->calendar()->slot(self::date('2026-12-12'), self::time('07:00')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function writtenTimes(): iterable
    {
        yield 'con i due punti' => ['09:30', '09:30'];
        yield 'senza lo zero' => ['9:30', '09:30'];
        yield 'con il punto' => ['09.30', '09:30'];
        yield 'con il punto e senza lo zero' => ['9.30', '09:30'];
        yield 'con i secondi' => ['09:30:00', '09:30'];
        yield 'con i millesimi' => ['09:30:00.000', '09:30'];
        yield 'con gli spazi' => [' 21:15 ', '21:15'];
        yield 'mezzanotte' => ['0:00', '00:00'];
    }

    #[DataProvider('writtenTimes')]
    public function testTheTimeIsReadAsTheTimePickerSendsItAndAsItIsWrittenByHand(string $written, string $time): void
    {
        $added = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno-settimana' => '7', 'orario' => $written]);

        self::assertSame(303, $added->status);
        self::assertStringContainsString('aria-label="Togli le ' . $time . ' dalla domenica"', self::card($this->browser->follow($added)->body, 7));
        self::assertNotNull($this->fixture->calendar()->slot(self::date('2026-12-13'), self::time($time)));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function wrongTimes(): iterable
    {
        $format = 'Scrivi l&#039;orario a 24 ore, come 18:30.';

        yield 'vuoto' => ['', 'Scrivi l&#039;orario della fascia, come 18:30.', ''];
        yield 'solo spazi' => ['   ', 'Scrivi l&#039;orario della fascia, come 18:30.', '   '];
        yield 'a parole' => ['sei e mezza', $format, 'sei e mezza'];
        yield 'senza i minuti' => ['18', $format, '18'];
        yield 'un\'ora che non c\'è' => ['25:00', $format, '25:00'];
        yield 'le 24' => ['24:00', $format, '24:00'];
        yield 'minuti che non ci sono' => ['18:60', $format, '18:60'];
        yield 'con i secondi' => ['18:30:15', $format, '18:30:15'];
        yield 'all\'inglese' => ['6:30pm', $format, '6:30pm'];
        yield 'già nella settimana tipo' => ['18:30', 'Il lunedì c&#039;è già la fascia delle 18:30.', '18:30'];
        yield 'già nella settimana tipo, scritta a mano' => ['18.30', 'Il lunedì c&#039;è già la fascia delle 18:30.', '18:30'];
        yield 'si sovrappone a un\'altra' => ['18:00', 'Le fasce delle 17:30 e delle 18:00 si sovrappongono: ogni fascia dura 60 minuti.', '18:00'];
        yield 'oltre la mezzanotte' => ['23:30', 'La fascia delle 23:30 finirebbe dopo la mezzanotte: ogni fascia dura 60 minuti.', '23:30'];
    }

    #[DataProvider('wrongTimes')]
    public function testAWrongTimeSaysWhatToCorrectOnTheField(string $written, string $message, string $value): void
    {
        $response = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno-settimana' => '1', 'orario' => $written]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<title>Errore: 4Street · Gestione · Settimana tipo</title>', $response->body);
        self::assertStringContainsString('<h2 id="riepilogo-errori" class="fs-alert__title">Controlla questi campi</h2>', $response->body);
        self::assertStringContainsString('<li><a href="#orario-1">' . $message . '</a></li>', $response->body);
        $monday = self::card($response->body, 1);
        self::assertStringContainsString('<p class="fs-field__error" id="orario-1-errore"><svg', $monday);
        self::assertStringContainsString('<span class="sr-only">Errore: </span>' . $message . '</span></p>', $monday);
        // Il valore scritto torna nel campo (come lo vuole il campo, se si legge).
        self::assertStringContainsString('<input class="fs-input fs-hours__input" id="orario-1" name="orario" type="time" value="' . $value . '" required aria-invalid="true" aria-describedby="orario-1-errore">', $monday);
        self::assertStringNotContainsString('aria-invalid', self::card($response->body, 2));
        self::assertSame(0, $this->fixture->count('schedule_weekly_slots'), 'Niente di salvato: l\'orario resta quello del file.');
    }

    public function testAtMostTwentyFourSlotsADay(): void
    {
        $hours = array_map(static fn(int $hour): SlotTime => self::time(sprintf('%02d:00', $hour)), range(0, 23));
        $this->schedule()->saveWeekday(Weekday::Sunday, $hours, self::date('2026-11-26'), null, $this->fixture->clock->now());
        self::assertStringContainsString('<p class="fs-hours__total">24 fasce</p>', self::card($this->browser->get(self::PAGE)->body, 7));

        $response = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno-settimana' => '7', 'orario' => '12:30']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<li><a href="#orario-7">Al massimo 24 fasce al giorno.</a></li>', $response->body);
    }

    public function testATimeToRemoveThatIsNoLongerThereSaysSoOnTheDay(): void
    {
        // Lo stesso bottone toccato due volte (senza JavaScript): la seconda volta l'orario non c'è più.
        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno-settimana' => '2', 'orario' => '20:00'])->status);

        $again = $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno-settimana' => '2', 'orario' => '20:00']);

        self::assertSame(422, $again->status);
        self::assertStringContainsString('<li><a href="#giorno-2">Il martedì non c&#039;è la fascia delle 20:00.</a></li>', $again->body);
        $tuesday = self::card($again->body, 2);
        self::assertStringContainsString('<p class="fs-field__error" id="giorno-2-errore">', $tuesday);
        self::assertStringContainsString('<p class="fs-hours__total">5 fasce</p>', $tuesday);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function tamperedForms(): iterable
    {
        yield 'azione sconosciuta' => [['azione' => 'sposta', 'giorno-settimana' => '1', 'orario' => '15:00']];
        yield 'azione di un\'altra pagina' => [['azione' => 'chiudi', 'giorno-settimana' => '1', 'orario' => '15:00']];
        yield 'senza azione' => [['giorno-settimana' => '1', 'orario' => '15:00']];
        yield 'senza giorno' => [['azione' => 'aggiungi', 'orario' => '15:00']];
        yield 'giorno otto' => [['azione' => 'aggiungi', 'giorno-settimana' => '8', 'orario' => '15:00']];
        yield 'giorno zero' => [['azione' => 'aggiungi', 'giorno-settimana' => '0', 'orario' => '15:00']];
        yield 'giorno con lo zero davanti' => [['azione' => 'aggiungi', 'giorno-settimana' => '01', 'orario' => '15:00']];
        yield 'giorno a parole' => [['azione' => 'aggiungi', 'giorno-settimana' => 'lunedì', 'orario' => '15:00']];
        yield 'orario di un chip scritto male' => [['azione' => 'togli', 'giorno-settimana' => '1', 'orario' => '7:00']];
        yield 'orario di un chip vuoto' => [['azione' => 'togli', 'giorno-settimana' => '1', 'orario' => '']];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('tamperedForms')]
    public function testATamperedFormIsNotFoundAndChangesNothing(array $fields): void
    {
        $response = $this->browser->post(self::PAGE, $fields);

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Settimana tipo.</h1>', $response->body);
        self::assertStringContainsString('<div class="fs-alert fs-alert--error" role="alert">', $response->body);
        self::assertStringContainsString('<p class="fs-alert__title">Modifica non valida</p>', $response->body);
        self::assertStringContainsString('<p>Il modulo è arrivato incompleto o scritto male, e non è cambiato nulla: ricarica la pagina e riprova.</p>', $response->body);
        self::assertSame(0, $this->fixture->count('schedule_weekly_slots'));
        self::assertSame([], $this->fixture->reporter->reported);
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeChanging(): void
    {
        $fields = ['azione' => 'aggiungi', 'giorno-settimana' => '7', 'orario' => '10:00'];

        $forged = $this->browser->post(self::PAGE, $fields, ['Sec-Fetch-Site' => 'cross-site']);
        self::assertSame(403, $forged->status);
        self::assertStringContainsString('Il modulo è scaduto.</h1>', $forged->body);
        self::assertSame(403, $this->browser->post(self::PAGE, [...$fields, 'csrf' => str_repeat('A', 43)])->status);

        self::assertSame(0, $this->fixture->count('schedule_weekly_slots'));
        self::assertNull($this->fixture->calendar()->slot(self::date('2026-12-13'), self::time('10:00')));
    }

    public function testTooManyChangesInAQuarterOfAnHourWait(): void
    {
        // Centocinquanta modifiche ogni quindici minuti, anche quelle che non si fanno: largo, ferma solo un uso fuori misura.
        for ($attempt = 1; $attempt <= 150; ++$attempt) {
            self::assertSame(422, $this->browser->post(self::PAGE, ['azione' => 'togli', 'giorno-settimana' => '7', 'orario' => '10:00'])->status, (string) $attempt);
        }

        $throttled = $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno-settimana' => '7', 'orario' => '10:00']);

        self::assertSame(429, $throttled->status);
        self::assertSame('900', $throttled->header('Retry-After'));
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $throttled->body);
        self::assertStringContainsString('Settimana tipo.</h1>', $throttled->body, 'La stessa pagina, con l\'avviso.');
        self::assertStringContainsString('id="orario-7" name="orario" type="time" value="10:00"', $throttled->body);
        self::assertNull($this->fixture->calendar()->slot(self::date('2026-12-13'), self::time('10:00')));

        $this->fixture->clock->travel('+15 minutes');
        self::assertSame(303, $this->browser->post(self::PAGE, ['azione' => 'aggiungi', 'giorno-settimana' => '7', 'orario' => '10:00'])->status);
    }

    public function testTheNoticeShowsOnlyBookingsTheGymCancelledAndNeverBreaks(): void
    {
        $admin = $this->fixture->user('titolare@example.test');
        $active = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-07', '18:30');
        $byCustomer = $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-08', '18:30');
        $this->fixture->database->execute(
            "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:30:00', cancelled_by = 'customer' WHERE id = :id",
            ['id' => $byCustomer->id],
        );
        $guest = $this->fixture->bookGuest($admin, 'Marco Neri', '2026-12-09', '07:00');
        $this->fixture->database->execute(
            "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:30:00', cancelled_by = 'admin' WHERE id = :id",
            ['id' => $guest->id],
        );

        // Numeri manomessi: una prenotazione attiva, una disdetta dal cliente, numeri scritti male, un conto che non è un numero.
        $page = $this->browser->get(self::PAGE . '?giorno-settimana=1&avviso=orario-tolto&disdette=abc&senza-account=' . $active->id . ',x,0,-3,99999999999999999999&senza-email=' . $byCustomer->id . ',' . $guest->id);
        self::assertSame(200, $page->status);
        $monday = self::card($page->body, 1);
        self::assertStringContainsString('<p class="fs-alert__title">Orario tolto</p>', $monday);
        self::assertStringNotContainsString('Anna Bianchi', $page->body);
        self::assertStringNotContainsString('Luca Verdi', $page->body);
        self::assertStringNotContainsString('Marco Neri', $page->body, 'Una persona senza account non sta fra le email non partite.');
        self::assertStringNotContainsString('disdette', $page->body);

        // Una prenotazione davvero disdetta dalla palestra, al posto giusto, si vede; il conto non è mai più piccolo dell'elenco.
        $listed = $this->browser->get(self::PAGE . '?giorno-settimana=1&avviso=orario-tolto&disdette=1&senza-account=' . $guest->id . ',' . $guest->id);
        self::assertStringContainsString('<p>Una prenotazione disdetta.</p>', $listed->body);
        self::assertSame(1, substr_count($listed->body, '<span class="fs-changes__who">Marco Neri</span>'));

        // Senza il giorno l'avviso sta in cima; un avviso sconosciuto non c'è; "nulla da cambiare" non ha mai disdette.
        $top = $this->browser->get(self::PAGE . '?giorno-settimana=9&avviso=orario-aggiunto')->body;
        self::assertLessThan(strpos($top, 'id="giorno-1"'), strpos($top, '<p class="fs-alert__title">Orario aggiunto</p>'));
        self::assertStringNotContainsString('<div class="fs-alert', $this->browser->get(self::PAGE . '?avviso=orario-cancellato')->body);
        $nothing = $this->browser->get(self::PAGE . '?avviso=nulla-da-cambiare&disdette=4&senza-account=' . $guest->id)->body;
        self::assertStringContainsString('<div class="fs-alert fs-alert--info" role="status">', $nothing);
        self::assertStringContainsString('<p>L&#039;orario era già così: non è cambiato nulla.</p>', $nothing);
        self::assertStringNotContainsString('Marco Neri', $nothing);
        self::assertSame([], $this->fixture->reporter->reported);
    }

    /** La scheda di un giorno della settimana tipo, dal suo id alla fine della sezione. */
    private static function card(string $html, int $day): string
    {
        self::assertSame(1, preg_match('#<section class="fs-hours__day" id="giorno-' . $day . '".*?</section>#s', $html, $match), 'Nessuna scheda del giorno ' . $day);

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
