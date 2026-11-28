<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Admin;

use FourStreet\Account\User;
use FourStreet\Admin\AdminModule;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Http\CancellationsController;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ResendNotice;
use FourStreet\Admin\Http\ScheduleNotice;
use FourStreet\Application;
use FourStreet\Booking\Booking;
use FourStreet\Booking\Staff\CancellationSheet;
use FourStreet\Booking\Staff\GymCancellations;
use FourStreet\Booking\Staff\GymEmailSender;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La pagina Disdette del pannello, attraverso l'applicazione vera: le prenotazioni disdette dalla palestra con la fascia
 * ancora da cominciare, per giorno, con com'è andata l'email di ognuno (arrivata, senza account, non partita, ancora da
 * inviare, senza esito); "Invia le email rimaste" con l'avviso dopo; l'avviso di una modifica dell'orario che porta qui
 * quando restano email. E le protezioni: token CSRF, limite dei tentativi, avvisi manomessi.
 * L'orologio parte da mercoledì 25 novembre 2026, 08:00.
 */
#[CoversClass(Application::class)]
#[CoversClass(AdminModule::class)]
#[CoversClass(AdminUrls::class)]
#[CoversClass(CancellationsController::class)]
#[CoversClass(ResendNotice::class)]
#[CoversClass(ChangeReport::class)]
#[CoversClass(ScheduleNotice::class)]
#[CoversClass(GymCancellations::class)]
#[CoversClass(GymEmailSender::class)]
#[CoversClass(CancellationSheet::class)]
final class AdminCancellationsTest extends TestCase
{
    private const string PAGE = '/gestione/disdette/';

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

    public function testAnEmptyPageSaysSo(): void
    {
        $page = $this->browser->get(self::PAGE);

        self::assertSame(200, $page->status);
        self::assertSame('no-store', $page->header('Cache-Control'));
        self::assertStringContainsString('<title>4Street · Gestione · Disdette</title>', $page->body);
        self::assertStringContainsString('Disdette.</h1>', $page->body);
        self::assertStringContainsString('Le prenotazioni disdette dalla palestra, finché la fascia non comincia: a chi è partita l&#039;email, e chi va ancora avvisato.</p>', $page->body);
        self::assertStringContainsString('<a href="/gestione/disdette/" class="fs-adminnav__link" aria-current="page">Disdette</a>', $page->body);
        self::assertStringContainsString('<p class="text-body text-fg-muted">Nessuna prenotazione disdetta dalla palestra nelle prossime fasce.</p>', $page->body);
        self::assertStringNotContainsString('<form method="post"', $page->body, 'Nessuna email da mandare: niente bottone.');
        self::assertSame(200, $this->browser->head(self::PAGE)->status);
    }

    public function testTheCancellationsOfTheGymByDayWithHowTheirEmailWent(): void
    {
        $this->fixture->mailer->unreachable = ['luca@example.test'];
        $anna = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-09', '18:30');
        $marco = $this->fixture->bookGuest($this->admin, 'Marco Neri', '2026-12-09', '07:00');
        $luca = $this->fixture->book($this->fixture->customer('luca@example.test', 'Luca Verdi'), '2026-12-10', '07:00');
        $this->closeDay('2026-12-09', [$marco, $anna]);
        self::assertSame(303, $this->browser->post(AdminUrls::cancel($luca->id))->status);
        // Disdetta dalla palestra prima che il sito registrasse l'email: senza esito.
        $before = $this->fixture->book($this->fixture->customer('bea@example.test', 'Bea Gialli'), '2026-12-11', '07:00');
        $this->fixture->database->execute(
            "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:30:00', cancelled_by = 'admin' WHERE id = :id",
            ['id' => $before->id],
        );
        // Non ci sono: una prenotazione attiva, e una disdetta dal cliente.
        $this->fixture->book($this->fixture->customer('sara@example.test', 'Sara Neri'), '2026-12-10', '09:30');
        $byCustomer = $this->fixture->book($this->fixture->customer('gio@example.test', 'Gio Rossi'), '2026-12-10', '12:30');
        $this->fixture->database->execute(
            "UPDATE bookings SET seat = NULL, active = NULL, cancelled_at = '2026-11-25 07:30:00', cancelled_by = 'customer' WHERE id = :id",
            ['id' => $byCustomer->id],
        );

        $page = $this->browser->get(self::PAGE)->body;

        // In cima quante email restano, e il bottone per mandarle.
        self::assertStringContainsString('<p>Un&#039;email non è ancora partita. Il bottone la manda adesso; oppure avvisa tu quel cliente.</p>', $page);
        self::assertStringContainsString('<form method="post" action="/gestione/disdette/" class="fs-actions" data-submit-once>', $page);
        self::assertStringContainsString('<button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Invia le email rimaste</button>', $page);

        // Per giorno, ognuno con com'è andata l'email, a parole e con l'icona.
        self::assertStringContainsString('<h2 id="disdette-titolo" class="fs-label text-fg-subtle">4 prenotazioni disdette</h2>', $page);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="disdette-giorno-0">Mercoledì 9 dicembre</h3>', $page);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="disdette-giorno-1">Giovedì 10 dicembre</h3>', $page);
        self::assertStringContainsString('<h3 class="fs-changes__date" id="disdette-giorno-2">Venerdì 11 dicembre</h3>', $page);
        self::assertStringContainsString(self::person('07:00', 'Marco Neri', 'Senza account', 'todo', 'alert', 'Da avvisare tu'), $page);
        self::assertStringContainsString(self::person('18:30', 'Anna Bianchi', 'anna@example.test', 'done', 'check', 'Email inviata'), $page);
        self::assertStringContainsString(self::person('07:00', 'Luca Verdi', 'luca@example.test', 'todo', 'alert', 'Email non partita: da rimandare, o da avvisare tu'), $page);
        self::assertStringContainsString(self::person('07:00', 'Bea Gialli', 'bea@example.test', 'none', 'info', 'Esito dell&#039;email non registrato'), $page);
        self::assertLessThan(strpos($page, 'Anna Bianchi'), strpos($page, 'Marco Neri'), 'Per fascia.');
        self::assertStringNotContainsString('Sara Neri', $page);
        self::assertStringNotContainsString('Gio Rossi', $page);
        self::assertStringNotContainsString('ferie', $page, 'La nota della chiusura non serve qui.');

        // Cominciata la fascia, la disdetta esce dalla pagina.
        $this->fixture->clock->moveTo('2026-12-09 18:30:00');
        $later = $this->browser->get(self::PAGE)->body;
        self::assertStringNotContainsString('Anna Bianchi', $later);
        self::assertStringContainsString('Luca Verdi', $later);
    }

    public function testTheRemainingEmailsLeaveFromThePageAndTheNoticeSaysHowMany(): void
    {
        $this->fixture->mailer->failing = true;
        $people = [];
        foreach (['07:00', '09:30', '12:30'] as $index => $time) {
            $people[] = $this->fixture->book($this->fixture->customer('cliente' . $index . '@example.test', 'Cliente Prova'), '2026-12-09', $time);
        }
        $location = $this->closeDay('2026-12-09', $people);

        // Il server di posta non risponde: due invii falliti di fila, la terza resta in attesa. L'avviso lo dice, e porta qui.
        self::assertStringContainsString('&disdette=3&da-avvisare=' . $people[0]->id . '%2C' . $people[1]->id . '%2C' . $people[2]->id, $location);
        $notice = $this->browser->get($location)->body;
        self::assertStringContainsString('<p>3 prenotazioni disdette.</p>', $notice, 'Mai "è arrivata l\'email" quando non è vero.');
        self::assertSame(2, substr_count($notice, ' · non partita</span>'));
        self::assertSame(1, substr_count($notice, ' · ancora da inviare</span>'));
        self::assertStringContainsString('<a href="/gestione/disdette/" class="fs-btn fs-btn--secondary fs-btn--sm mt-2 self-start">Vai alle disdette</a>', $notice);

        $page = $this->browser->get(self::PAGE)->body;
        self::assertStringContainsString('<p>3 email non sono ancora partite. Il bottone le manda adesso, poche per volta: se ne restano, premilo di nuovo. Oppure avvisa tu quei clienti.</p>', $page);
        self::assertSame(2, substr_count($page, 'Email non partita: da rimandare, o da avvisare tu'));
        self::assertSame(1, substr_count($page, 'Email ancora da inviare: da mandare, o da avvisare tu'));

        // Il server di posta torna: partono tutte e tre, con il perché dell'orario di adesso.
        $this->fixture->mailer->failing = false;
        $sent = $this->browser->post(self::PAGE);
        self::assertSame(303, $sent->status);
        self::assertSame('/gestione/disdette/?avviso=email-inviate&partite=3', $sent->header('Location'));
        self::assertCount(3, $this->fixture->mailer->sent);
        self::assertStringContainsString('Quel giorno la palestra resta chiusa.', $this->fixture->mailer->lastTo('cliente2@example.test')->text);

        $after = $this->browser->follow($sent)->body;
        self::assertStringContainsString('<p class="fs-alert__title">Email inviate</p>', $after);
        self::assertStringContainsString('<p>Sono partite 3 email. Non ne resta nessuna da inviare.</p>', $after);
        self::assertSame(3, substr_count($after, 'Email inviata'));
        self::assertStringNotContainsString('Invia le email rimaste', $after);

        // Ricaricare non rimanda nulla, e nemmeno un secondo invio del modulo: le email partite non si rimandano.
        $this->browser->get('/gestione/disdette/?avviso=email-inviate&partite=3');
        $again = $this->browser->post(self::PAGE);
        self::assertSame('/gestione/disdette/?avviso=nessuna-email-da-inviare', $again->header('Location'));
        self::assertStringContainsString('<p>Non c&#039;era nessuna email in attesa o non partita da inviare.</p>', $this->browser->follow($again)->body);
        self::assertCount(3, $this->fixture->mailer->sent);

        // E l'avviso della chiusura, riaperto, dice com'è adesso.
        $reopened = $this->browser->get($location)->body;
        self::assertStringContainsString('<p>3 prenotazioni disdette. Ai clienti registrati è arrivata l&#039;email della palestra, con il motivo.</p>', $reopened);
        self::assertStringNotContainsString('Vai alle disdette', $reopened);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function notices(): iterable
    {
        yield 'giro finito, una da inviare' => ['?avviso=email-inviate&partite=2', 'fs-alert--info', '<p>Sono partite 2 email. Ne resta una da inviare: riprova più tardi, oppure avvisa tu quei clienti.</p>'];
        yield 'tempo finito' => ['?avviso=email-tempo-finito&partite=1', 'fs-alert--info', '<p>È partita un&#039;email. Ne resta una: si mandano poche email per volta, perché la pagina non resti ad aspettare. Premi di nuovo «Invia le email rimaste».</p>'];
        yield 'server fermo' => ['?avviso=email-server-fermo&partite=0', 'fs-alert--error', '<p>Il server di posta non risponde. Non è partita nessuna email. Ne resta una: riprova più tardi, oppure avvisa tu quei clienti.</p>'];
        yield 'conto manomesso' => ['?avviso=email-inviate&partite=-4', 'fs-alert--info', '<p>Non è partita nessuna email. Ne resta una da inviare: riprova più tardi, oppure avvisa tu quei clienti.</p>'];
    }

    #[DataProvider('notices')]
    public function testTheNoticeAfterSendingSaysHowManyLeftAndHowManyRemainNow(string $query, string $tone, string $message): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test', 'Anna Bianchi'), '2026-12-09', '18:30');
        $this->fixture->mailer->failing = true;
        self::assertSame(303, $this->browser->post(AdminUrls::cancel($booking->id))->status);

        // Quante restano lo dice la pagina: una, non partita.
        $page = $this->browser->get(self::PAGE . $query)->body;

        self::assertStringContainsString('<div class="fs-alert ' . $tone . '"', $page);
        self::assertStringContainsString($message, $page);
    }

    public function testWithTheMailServerDownTheSendStopsAndSaysSo(): void
    {
        $people = [];
        foreach (['07:00', '09:30', '12:30'] as $index => $time) {
            $booking = $this->fixture->book($this->fixture->customer('cliente' . $index . '@example.test'), '2026-12-09', $time);
            $this->fixture->mailer->failing = true;
            self::assertSame(303, $this->browser->post(AdminUrls::cancel($booking->id))->status);
            $people[] = $booking;
        }

        // Due invii falliti di fila: il giro si ferma, e la terza non si prova nemmeno.
        $sent = $this->browser->post(self::PAGE);

        self::assertSame('/gestione/disdette/?avviso=email-server-fermo&partite=0', $sent->header('Location'));
        self::assertSame(['failed', 'failed', 'failed'], array_map($this->gymEmail(...), $people));
        self::assertCount(5, $this->fixture->reporter->reported, 'Tre alla disdetta, due adesso.');
        $page = $this->browser->follow($sent)->body;
        self::assertStringContainsString('<div class="fs-alert fs-alert--error" role="alert">', $page);
        self::assertStringContainsString('<p>Il server di posta non risponde. Non è partita nessuna email. Ne restano 3: riprova più tardi, oppure avvisa tu quei clienti.</p>', $page);
    }

    public function testWhenNothingLeftAndNothingRemainsTheNoticeIsNoSuccess(): void
    {
        // Per esempio: le ha mandate un altro invio nello stesso istante, o le loro fasce sono cominciate.
        $page = $this->browser->get(self::PAGE . '?avviso=email-inviate&partite=0')->body;

        self::assertStringContainsString('<div class="fs-alert fs-alert--info" role="status">', $page);
        self::assertStringContainsString('<p class="fs-alert__title">Nulla da inviare</p>', $page);
        self::assertStringContainsString('<p>Non è partita nessuna email. Non ne resta nessuna da inviare.</p>', $page);
    }

    public function testAnUnknownNoticeShowsNothing(): void
    {
        self::assertStringNotContainsString('<div class="fs-alert', $this->browser->get(self::PAGE . '?avviso=email-cancellate&partite=3')->body);
    }

    public function testAFormFromAnotherSiteIsRejectedBeforeSending(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test'), '2026-12-09', '18:30');
        $this->fixture->mailer->failing = true;
        $this->browser->post(AdminUrls::cancel($booking->id));
        $this->fixture->mailer->failing = false;

        $forged = $this->browser->post(self::PAGE, [], ['Sec-Fetch-Site' => 'cross-site']);
        $withoutToken = $this->browser->post(self::PAGE, ['csrf' => '']);

        self::assertSame(403, $forged->status);
        self::assertSame(403, $withoutToken->status);
        self::assertSame([], $this->fixture->mailer->sent);
        self::assertSame('failed', $this->gymEmail($booking));
    }

    public function testTooManySendsInAQuarterOfAnHourWait(): void
    {
        $booking = $this->fixture->book($this->fixture->customer('anna@example.test'), '2026-12-09', '18:30');
        $this->fixture->mailer->failing = true;
        $this->browser->post(AdminUrls::cancel($booking->id));
        $this->fixture->mailer->failing = false;
        // Lo stesso contatore delle modifiche dell'orario: 150 ogni 15 minuti.
        for ($attempt = 1; $attempt <= 150; ++$attempt) {
            self::assertSame(422, $this->browser->post('/gestione/chiusure/', ['azione' => 'riapri', 'da' => '2026-12-24', 'a' => '2026-12-24'])->status, (string) $attempt);
        }

        $throttled = $this->browser->post(self::PAGE);

        self::assertSame(429, $throttled->status);
        self::assertSame('900', $throttled->header('Retry-After'));
        self::assertStringContainsString('Disdette.</h1>', $throttled->body);
        self::assertStringContainsString('Troppi tentativi in poco tempo. Riprova tra 15 minuti.', $throttled->body);
        self::assertSame([], $this->fixture->mailer->sent);
        self::assertSame('failed', $this->gymEmail($booking));
    }

    /**
     * Chiude un giorno dalla pagina delle chiusure, con la conferma delle prenotazioni di quel giorno.
     *
     * @param list<Booking> $bookings le prenotazioni del giorno, che la chiusura disdice
     *
     * @return string dove porta la chiusura, con l'avviso
     */
    private function closeDay(string $date, array $bookings): string
    {
        $ids = array_map(static fn(Booking $booking): int => $booking->id, $bookings);
        sort($ids);
        $closed = $this->browser->post('/gestione/chiusure/', ['azione' => 'chiudi', 'da' => $date, 'a' => '', 'nota' => 'ferie', 'prenotazioni' => implode(',', $ids)]);
        self::assertSame(303, $closed->status);

        return (string) $closed->header('Location');
    }

    /** Una persona della pagina Disdette, dall'orario all'esito dell'email. */
    private static function person(string $time, string $name, string $meta, string $tone, string $icon, string $state): string
    {
        return '<span class="fs-changes__time"><span class="sr-only">Alle </span>' . $time . '</span>' . "\n"
            . '        <p class="fs-changes__name">' . $name . '</p>' . "\n"
            . '        <p class="fs-changes__meta">' . $meta . '</p>' . "\n"
            . '        <p class="fs-changes__state fs-changes__state--' . $tone . '"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">'
            . self::iconPaths($icon) . '</svg><span>' . $state . '</span></p>';
    }

    private static function iconPaths(string $icon): string
    {
        return match ($icon) {
            'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"></path>',
            'alert' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7.5v6"></path><path d="M12 16.5h.01"></path>',
            default => '<circle cx="12" cy="12" r="9"></circle><path d="M12 10.5v6"></path><path d="M12 7.5h.01"></path>',
        };
    }

    private function gymEmail(Booking $booking): ?string
    {
        return $this->fixture->database->fetchOne('SELECT gym_email FROM bookings WHERE id = :id', ['id' => $booking->id])?->nullableString('gym_email');
    }
}
