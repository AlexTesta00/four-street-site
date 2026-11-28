<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Admin;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\Role;
use FourStreet\Account\SignInDestination;
use FourStreet\Account\User;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\StaffBookingForm;
use FourStreet\Admin\Http\AdminNotice;
use FourStreet\Admin\Http\ResendNotice;
use FourStreet\Booking\Booking;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\GymEmailRound;
use FourStreet\Booking\Staff\GymEmailStop;
use FourStreet\Booking\Staff\ResendOutcome;
use FourStreet\Http\Request;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * I valori del pannello del titolare: gli indirizzi (mai un nome), gli avvisi (quello di una disdetta con l'email com'è
 * adesso nel database, quello dopo "Invia le email rimaste"), il modulo con i due campi alternativi e la pagina dopo
 * l'accesso.
 */
#[CoversClass(AdminUrls::class)]
#[CoversClass(AdminNotice::class)]
#[CoversClass(ResendNotice::class)]
#[CoversClass(ResendOutcome::class)]
#[CoversClass(StaffBookingForm::class)]
#[CoversClass(SignInDestination::class)]
final class AdminValuesTest extends TestCase
{
    public function testTheAddressesCarryOnlyTheDayTheSlotAndTheBookingNumber(): void
    {
        $day = LocalDate::tryFrom('2026-12-07') ?? self::fail();
        $time = SlotTime::tryFrom('18:30') ?? self::fail();

        self::assertSame('/gestione/', AdminUrls::day());
        self::assertSame('/gestione/?giorno=2026-12-07', AdminUrls::day($day));
        self::assertSame('/gestione/?giorno=2026-12-07&avviso=prenotazione-disdetta&prenotazione=12', AdminUrls::day($day, AdminNotice::Cancelled, 12));
        self::assertSame('/gestione/prenotazioni/nuova/?giorno=2026-12-07&fascia=18%3A30', AdminUrls::newBooking($day, $time));
        self::assertSame('/gestione/prenotazioni/12/disdici/', AdminUrls::cancel(12));
    }

    public function testEachNoticeNamesThePersonOnlyFromTheDatabase(): void
    {
        $dates = new ItalianDateFormatter();
        $customer = new RosterEntry(self::booking(userId: 7), 'Anna Bianchi', EmailAddress::tryFrom('anna@example.test'));
        $guest = new RosterEntry(self::booking(guestName: 'Marco Neri'), 'Marco Neri', null);

        self::assertSame('Anna Bianchi, lunedì 7 dicembre alle 18:30. Gli abbiamo mandato l\'email di conferma, e la trova nel suo account.', AdminNotice::Booked->alert($customer, $dates)->message);
        self::assertSame('Marco Neri, lunedì 7 dicembre alle 18:30. Non ha l\'account: la prenotazione la vedi solo qui.', AdminNotice::Booked->alert($guest, $dates)->message);
        self::assertSame('Anna Bianchi, lunedì 7 dicembre alle 18:30. Il posto è tornato libero. Gli abbiamo mandato un\'email per avvisarlo.', AdminNotice::Cancelled->alert($customer, $dates)->message);
        self::assertSame('Marco Neri, lunedì 7 dicembre alle 18:30. Il posto è tornato libero. Non ha l\'account: avvisalo tu.', AdminNotice::Cancelled->alert($guest, $dates)->message);
        self::assertSame('L\'email di conferma non è partita: avvisalo tu. La prenotazione vale.', AdminNotice::BookedWithoutEmail->alert(null, $dates)->message);
        self::assertSame('Anna Bianchi, lunedì 7 dicembre alle 18:30. Era già in questa fascia: non è cambiato nulla.', AdminNotice::AlreadyBooked->alert($customer, $dates)->message);
        self::assertSame(
            'Marco Neri, lunedì 7 dicembre alle 18:30. Era già in questa fascia: non è cambiato nulla. Se è un\'altra persona con lo stesso nome, aggiungi l\'iniziale del cognome.',
            AdminNotice::AlreadyBooked->alert($guest, $dates)->message,
        );

        foreach (AdminNotice::cases() as $notice) {
            $alert = $notice->alert(null, $dates);
            self::assertNotSame('', $alert->message);
            self::assertNotNull($alert->title);
            self::assertSame(in_array($notice, [AdminNotice::AlreadyBooked, AdminNotice::AlreadyCancelled], true) ? 'info' : 'success', $alert->tone);
            self::assertSame($notice, AdminNotice::fromRequest(Request::create('GET', '/gestione/?avviso=' . $notice->value)));
        }
        self::assertNull(AdminNotice::fromRequest(Request::create('GET', '/gestione/?avviso=uscito')), 'Gli avvisi dell\'area clienti non sono del pannello.');
    }

    public function testAfterACancellationTheNoticeSaysHowTheGymEmailIsNow(): void
    {
        $dates = new ItalianDateFormatter();
        $with = static fn(?GymEmail $email): RosterEntry => new RosterEntry(self::booking(userId: 7, gymEmail: $email), 'Anna Bianchi', EmailAddress::tryFrom('anna@example.test'));
        $lead = 'Anna Bianchi, lunedì 7 dicembre alle 18:30. Il posto è tornato libero. ';

        // Il database vince sull'indirizzo: rimandata da Disdette è partita, anche se al redirect non lo era.
        self::assertSame($lead . 'Gli abbiamo mandato un\'email per avvisarlo.', AdminNotice::CancelledWithoutEmail->alert($with(GymEmail::Sent), $dates)->message);
        self::assertSame($lead . 'L\'email di avviso non è partita: rimandala da Disdette, oppure avvisalo tu.', AdminNotice::Cancelled->alert($with(GymEmail::Failed), $dates)->message);
        foreach ([GymEmail::Pending, GymEmail::Sending] as $email) {
            self::assertSame($lead . 'L\'email di avviso non è ancora partita: controlla in Disdette.', AdminNotice::Cancelled->alert($with($email), $dates)->message);
        }

        // Senza esito nel database (una disdetta di prima che lo registrasse, o la prenotazione che non si trova) vale l'indirizzo.
        self::assertSame($lead . 'Gli abbiamo mandato un\'email per avvisarlo.', AdminNotice::Cancelled->alert($with(null), $dates)->message);
        self::assertSame($lead . 'L\'email di avviso non è partita: rimandala da Disdette, oppure avvisalo tu.', AdminNotice::CancelledWithoutEmail->alert($with(null), $dates)->message);
        self::assertSame('Il posto è tornato libero. L\'email di avviso non è partita: rimandala da Disdette, oppure avvisalo tu.', AdminNotice::CancelledWithoutEmail->alert(null, $dates)->message);
    }

    public function testTheNoticeAfterSendingTheRemainingEmailsCarriesOnlyHowManyLeft(): void
    {
        $round = static fn(?GymEmailStop $stopped): GymEmailRound => new GymEmailRound([10 => GymEmail::Sent, 11 => GymEmail::Failed, 12 => GymEmail::Sending], $stopped);

        // Com'è finito il giro sceglie l'avviso; nell'indirizzo, oltre all'avviso, solo quante email sono partite.
        self::assertSame(ResendNotice::Sent, ResendNotice::of(ResendOutcome::sent($round(null))));
        self::assertSame(ResendNotice::TimeUp, ResendNotice::of(ResendOutcome::sent($round(GymEmailStop::TimeUp))));
        self::assertSame(ResendNotice::MailDown, ResendNotice::of(ResendOutcome::sent($round(GymEmailStop::MailDown))));
        self::assertSame(ResendNotice::NothingToSend, ResendNotice::of(ResendOutcome::nothingToSend()));
        self::assertSame('/gestione/disdette/?avviso=email-tempo-finito&partite=1', AdminUrls::with(AdminUrls::CANCELLATIONS, ResendNotice::of(ResendOutcome::sent($round(GymEmailStop::TimeUp)))->query(1)));
        self::assertSame(['avviso' => 'nessuna-email-da-inviare'], ResendNotice::NothingToSend->query(0));

        // Dall'indirizzo: l'avviso fra i testi fissi, il conto solo se scritto bene.
        $request = static fn(string $query): Request => Request::create('GET', '/gestione/disdette/?' . $query);
        self::assertSame(ResendNotice::MailDown, ResendNotice::fromRequest($request('avviso=email-server-fermo&partite=2')));
        self::assertNull(ResendNotice::fromRequest($request('avviso=prenotazione-disdetta')));
        self::assertSame(2, ResendNotice::sentFrom($request('partite=2')));
        foreach (['partite=-1', 'partite=02', 'partite=due', 'partite=9999999', ''] as $query) {
            self::assertSame(0, ResendNotice::sentFrom($request($query)), $query);
        }

        // Partite e rimaste: un successo solo quando qualcosa è partito e non ne resta nessuna.
        self::assertSame(['success', 'Email inviate', 'Sono partite 3 email. Non ne resta nessuna da inviare.'], self::alert(ResendNotice::Sent->alert(3, 0)));
        self::assertSame(['info', 'Nulla da inviare', 'Non è partita nessuna email. Non ne resta nessuna da inviare.'], self::alert(ResendNotice::Sent->alert(0, 0)));
        self::assertSame(['info', 'Email inviate in parte', 'Sono partite 3 email. Ne restano 2: si mandano poche email per volta, perché la pagina non resti ad aspettare. Premi di nuovo «Invia le email rimaste».'], self::alert(ResendNotice::TimeUp->alert(3, 2)));
        self::assertSame(['error', 'Email non partite', 'Il server di posta non risponde. Non è partita nessuna email. Ne resta una: riprova più tardi, oppure avvisa tu quei clienti.'], self::alert(ResendNotice::MailDown->alert(0, 1)));

        $this->expectException(LogicException::class);
        ResendNotice::of(ResendOutcome::throttled(900));
    }

    public function testTheFormWantsExactlyOneOfEmailAndName(): void
    {
        $customer = StaffBookingForm::fromRequest(self::post(['email' => ' Anna@Example.TEST ', 'nome' => '  ']));
        self::assertTrue($customer->isValid());
        self::assertSame('anna@example.test', $customer->customerEmail()?->value);
        self::assertNull($customer->guestName());

        $guest = StaffBookingForm::fromRequest(self::post(['email' => '', 'nome' => '  Marco   Neri ']));
        self::assertTrue($guest->isValid());
        self::assertNull($guest->customerEmail());
        self::assertSame('Marco Neri', $guest->guestName()?->value);

        // Senza i campi (un modulo manomesso) è come vuoto.
        self::assertSame(['email' => 'Scrivi l\'email del cliente registrato, oppure il nome di chi non ha l\'account.'], StaffBookingForm::fromRequest(self::post([]))->errors->all());
        self::assertSame(['nome'], array_keys(StaffBookingForm::fromRequest(self::post(['email' => 'anna@example.test', 'nome' => 'Anna']))->errors->all()));

        $unknown = $customer->withUnknownCustomer();
        self::assertFalse($unknown->isValid());
        self::assertSame(' Anna@Example.TEST ', $unknown->email, 'Il valore scritto torna nel modulo.');
        self::assertStringStartsWith('Nessun cliente con questa email', (string) $unknown->errors->get('email'));
        self::assertStringContainsString('alle 07:00, e si prenota una fascia al giorno. Per spostarlo, disdici prima quella prenotazione.', (string) $customer->withBookingThatDay('07:00', cancellable: true)->errors->get('email'));
        self::assertSame(
            'Questo cliente quel giorno è già stato prenotato alle 07:00, e si prenota una fascia al giorno: quella fascia è già iniziata.',
            $customer->withBookingThatDay('07:00', cancellable: false)->errors->get('email'),
            'Non propone una disdetta che non c\'è più.',
        );
        self::assertTrue(StaffBookingForm::blank()->errors->isEmpty());

        $this->expectException(LogicException::class);
        $unknown->customerEmail();
    }

    public function testAfterTheLoginTheAdminGoesToThePanelUnlessAnotherPageWasAsked(): void
    {
        $destination = new SignInDestination('/gestione/');

        self::assertSame('/gestione/', $destination->for(self::user(Role::Admin), ReturnPath::default()));
        self::assertSame('/prenota/', $destination->for(self::user(Role::Admin), ReturnPath::fromInput('/prenota/')));
        self::assertSame('/account/', $destination->for(self::user(Role::Customer), ReturnPath::default()));
        self::assertSame('/account/', (new SignInDestination())->for(self::user(Role::Admin), ReturnPath::default()), 'Senza pannello, l\'account.');
    }

    private static function booking(?int $userId = null, ?string $guestName = null, ?GymEmail $gymEmail = null): Booking
    {
        $utc = new DateTimeZone('UTC');

        return new Booking(
            id: 3,
            userId: $userId,
            guestName: $guestName,
            date: LocalDate::tryFrom('2026-12-07') ?? self::fail(),
            time: SlotTime::tryFrom('18:30') ?? self::fail(),
            startsAt: new DateTimeImmutable('2026-12-07 17:30:00', $utc),
            seat: 2,
            createdAt: new DateTimeImmutable('2026-11-25 07:00:00', $utc),
            createdBy: $userId === null ? CreatedBy::Admin : CreatedBy::Customer,
            gymEmail: $gymEmail,
        );
    }

    /**
     * Il tono, il titolo e il testo di un avviso.
     *
     * @return array{string, string|null, string}
     */
    private static function alert(Alert $alert): array
    {
        return [$alert->tone, $alert->title, $alert->message];
    }

    private static function user(Role $role): User
    {
        $at = new DateTimeImmutable('2026-11-25 07:00:00', new DateTimeZone('UTC'));

        return new User(1, 'Titolare Prova', EmailAddress::tryFrom('titolare@example.test') ?? self::fail(), 'hash', $at, $at, $role, $at, $at);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function post(array $fields): Request
    {
        return Request::create('POST', '/gestione/prenotazioni/nuova/', [], $fields);
    }
}
