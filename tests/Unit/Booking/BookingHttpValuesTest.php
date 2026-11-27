<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\Http\BookingNotice;
use FourStreet\Booking\Http\SlotSelection;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Http\Request;
use FourStreet\View\Alert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Gli indirizzi delle prenotazioni, gli avvisi dell'account e la lettura della fascia dai parametri.
 */
#[CoversClass(BookingUrls::class)]
#[CoversClass(BookingNotice::class)]
#[CoversClass(SlotSelection::class)]
#[UsesClass(LocalDate::class)]
#[UsesClass(SlotTime::class)]
#[UsesClass(Request::class)]
#[UsesClass(Alert::class)]
final class BookingHttpValuesTest extends TestCase
{
    public function testTheAddresses(): void
    {
        $day = LocalDate::tryFrom('2026-12-07') ?? self::fail();

        self::assertSame('/prenota/', BookingUrls::day());
        self::assertSame('/prenota/?giorno=2026-12-07', BookingUrls::day($day));
        self::assertSame('/prenota/conferma/?giorno=2026-12-07&fascia=18%3A30', BookingUrls::confirm($day, SlotTime::tryFrom('18:30') ?? self::fail()));
        self::assertSame('/account/prenotazioni/42/disdici/', BookingUrls::cancel(42));
        self::assertSame('/account/?avviso=prenotazione-confermata&prenotazione=42', BookingUrls::account(BookingNotice::Booked, 42));
        self::assertSame('/account/?avviso=prenotazione-disdetta', BookingUrls::account(BookingNotice::Cancelled));
    }

    /**
     * @return iterable<string, array{string|null, int|null}>
     */
    public static function bookingIds(): iterable
    {
        yield 'un numero' => ['42', 42];
        yield 'il più piccolo' => ['1', 1];
        yield 'zero' => ['0', null];
        yield 'con lo zero davanti' => ['042', null];
        yield 'negativo' => ['-1', null];
        yield 'con una lettera' => ['4a', null];
        yield 'troppo lungo' => [str_repeat('9', 19), null];
        yield 'mancante' => [null, null];
    }

    #[DataProvider('bookingIds')]
    public function testABookingNumberInAnAddress(?string $value, ?int $id): void
    {
        self::assertSame($id, BookingUrls::bookingId($value));
    }

    public function testTheSlotFromTheQueryAndFromTheForm(): void
    {
        $query = SlotSelection::fromQuery(Request::create('GET', '/prenota/conferma/?giorno=2026-12-07&fascia=18%3A30'));
        self::assertSame('2026-12-07', $query->date?->value);
        self::assertSame('18:30', $query->time?->value);
        self::assertFalse($query->tampered);

        $form = SlotSelection::fromBody(Request::create('POST', '/prenota/conferma/', [], ['giorno' => '2026-12-08', 'fascia' => '07:00']));
        self::assertSame('2026-12-08', $form->date?->value);
        self::assertSame('07:00', $form->time?->value);

        $missing = SlotSelection::fromQuery(Request::create('GET', '/prenota/conferma/?giorno=2026-12-07'));
        self::assertNull($missing->time);
        self::assertFalse($missing->tampered, 'Un parametro che manca non è manomesso.');

        // Un array al posto del testo vale come mancante; un testo sbagliato è manomesso.
        self::assertNull(SlotSelection::fromQuery(Request::create('GET', '/prenota/conferma/?giorno[]=2026-12-07'))->date);
        self::assertTrue(SlotSelection::fromQuery(Request::create('GET', '/prenota/conferma/?giorno=2026-12-07&fascia=7'))->tampered);
        self::assertTrue(SlotSelection::fromBody(Request::create('POST', '/', [], ['giorno' => 'ieri', 'fascia' => '07:00']))->tampered);
    }

    public function testTheNoticesOfTheAccountPage(): void
    {
        self::assertSame(BookingNotice::Booked, BookingNotice::fromRequest(Request::create('GET', '/account/?avviso=prenotazione-confermata')));
        self::assertNull(BookingNotice::fromRequest(Request::create('GET', '/account/?avviso=email-confermata')), 'Gli avvisi dell\'area clienti non sono suoi.');
        self::assertNull(BookingNotice::fromRequest(Request::create('GET', '/account/')));

        $booked = BookingNotice::Booked->alert('Lunedì 7 dicembre alle 18:30');
        self::assertSame('success', $booked->tone);
        self::assertSame('Prenotazione confermata', $booked->title);
        self::assertSame('Lunedì 7 dicembre alle 18:30. Ti abbiamo mandato un\'email con i dettagli e l\'evento da aggiungere al calendario.', $booked->message);

        self::assertSame('L\'email di conferma non è partita, ma la prenotazione vale: la trovi qui sotto.', BookingNotice::BookedWithoutEmail->alert(null)->message);
        self::assertSame('info', BookingNotice::AlreadyYours->alert(null)->tone);
        self::assertSame('Prenotazione disdetta', BookingNotice::Cancelled->alert(null)->title);
        self::assertStringContainsString('non è partita', BookingNotice::CancelledWithoutEmail->alert(null)->message);
    }
}
