<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Booking\BookingRules;
use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Booking\WeeklySchedule;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\InvalidSiteConfig;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Le regole della prenotazione in config/site.php: si leggono così come le scrive il titolare,
 * e ogni errore dice quale chiave correggere.
 */
#[CoversClass(BookingRules::class)]
#[CoversClass(WeeklySchedule::class)]
#[CoversClass(ConfigValues::class)]
#[UsesClass(ExampleSlotCatalog::class)]
#[UsesClass(Slot::class)]
#[UsesClass(SlotTime::class)]
#[UsesClass(LocalDate::class)]
#[UsesClass(Weekday::class)]
#[UsesClass(InvalidSiteConfig::class)]
final class BookingRulesTest extends TestCase
{
    public function testTheProvisionalRules(): void
    {
        $rules = self::rules();

        self::assertTrue($rules->online);
        self::assertSame(5, $rules->capacity);
        self::assertSame(60, $rules->slotMinutes);
        self::assertSame(14, $rules->daysAhead);
        self::assertSame([], $rules->closedDates);
        self::assertSame(0, $rules->bookingClosesMinutesBefore);
        self::assertSame(0, $rules->cancelClosesMinutesBefore);
        self::assertSame(6, $rules->maxUpcoming);
        self::assertSame('18:30', $rules->examples->defaultSlot()->time);
        foreach (Weekday::cases() as $day) {
            self::assertSame(
                $day === Weekday::Sunday ? [] : ['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'],
                self::times($rules, $day),
                $day->label(),
            );
        }
    }

    public function testTheRealConfigurationFileHasTheSameRules(): void
    {
        $site = require SiteFixture::rootDirectory() . '/config/site.php';
        self::assertIsArray($site);
        self::assertIsArray($site['booking'] ?? null);

        // Stessi valori, oggetti diversi: si confrontano le loro forme serializzate.
        self::assertSame(serialize(self::rules()), serialize(BookingRules::fromConfig(ConfigValues::root($site['booking']))));
    }

    public function testTheScheduleIsReadAsTheOwnerWritesIt(): void
    {
        $rules = self::rules(['schedule' => [
            // Giorni senza accento, in maiuscolo, in un altro ordine; orari non in ordine.
            'Domenica' => [],
            'sabato' => ['10:00'],
            'venerdi' => ['20:00', '07:00'],
            'giovedi' => [],
            'mercoledi' => [],
            'martedi' => [],
            'lunedi' => ['23:00'],
        ]]);

        self::assertSame(['07:00', '20:00'], self::times($rules, Weekday::Friday));
        self::assertSame(['23:00'], self::times($rules, Weekday::Monday), 'Una fascia di un\'ora alle 23 finisce a mezzanotte.');
        self::assertSame([], self::times($rules, Weekday::Tuesday));
    }

    public function testClosedDates(): void
    {
        $rules = self::rules(['closed_dates' => ['2026-12-25', '2027-01-01']]);

        self::assertTrue($rules->isClosedOn(LocalDate::tryFrom('2026-12-25') ?? self::fail()));
        self::assertFalse($rules->isClosedOn(LocalDate::tryFrom('2026-12-24') ?? self::fail()));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function deadlines(): iterable
    {
        yield 'all\'inizio' => [0, 'fino all\'inizio della fascia'];
        yield 'un minuto' => [1, 'fino a un minuto prima dell\'inizio'];
        yield 'mezz\'ora' => [30, 'fino a 30 minuti prima dell\'inizio'];
        yield 'un\'ora' => [60, 'fino a un\'ora prima dell\'inizio'];
        yield 'novanta minuti' => [90, 'fino a 90 minuti prima dell\'inizio'];
        yield 'un giorno' => [1440, 'fino a 24 ore prima dell\'inizio'];
    }

    #[DataProvider('deadlines')]
    public function testTheDeadlinesInWords(int $minutes, string $words): void
    {
        $rules = self::rules(['booking_closes_minutes_before' => $minutes, 'cancel_closes_minutes_before' => $minutes]);

        self::assertSame($words, $rules->bookingDeadline());
        self::assertSame($words, $rules->cancellationDeadline());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidRules(): iterable
    {
        $week = SiteFixture::week();

        yield 'chiave sconosciuta' => [['slots' => []], 'chiavi sconosciute in "booking" (slots)'];
        yield 'interruttore come testo' => [['online' => 'si'], '"booking.online" deve essere true oppure false (trovato: "si")'];
        yield 'capienza zero' => [['capacity' => 0], '"booking.capacity" deve essere un numero intero maggiore di zero (trovato: "0")'];
        yield 'capienza come testo' => [['capacity' => '5'], '"booking.capacity" deve essere un numero intero maggiore di zero'];
        yield 'durata zero' => [['slot_minutes' => 0], '"booking.slot_minutes" deve essere un numero intero da 1 a 1440 (trovato: "0")'];
        yield 'durata di due giorni' => [['slot_minutes' => 2880], '"booking.slot_minutes" deve essere un numero intero da 1 a 1440 (trovato: "2880")'];
        yield 'giorni prenotabili negativi' => [['days_ahead' => -1], '"booking.days_ahead" deve essere un numero intero da 0 a 62 (trovato: "-1")'];
        yield 'giorni prenotabili come testo' => [['days_ahead' => '14'], '"booking.days_ahead" deve essere un numero intero da 0 a 62 (trovato: "14")'];
        yield 'troppi giorni prenotabili' => [['days_ahead' => 140], '"booking.days_ahead" deve essere un numero intero da 0 a 62 (trovato: "140")'];
        yield 'chiusura negativa' => [['booking_closes_minutes_before' => -5], '"booking.booking_closes_minutes_before" deve essere un numero intero da 0 a 10080'];
        yield 'disdetta oltre la settimana' => [['cancel_closes_minutes_before' => 20000], '"booking.cancel_closes_minutes_before" deve essere un numero intero da 0 a 10080'];
        yield 'nessuna prenotazione futura' => [['max_upcoming' => 0], '"booking.max_upcoming" deve essere un numero intero maggiore di zero'];
        yield 'chiusure non elenco' => [['closed_dates' => '2026-12-25'], '"booking.closed_dates" deve essere un elenco di giorni nel formato AAAA-MM-GG'];
        yield 'chiusura scritta male' => [['closed_dates' => ['25/12/2026']], '"booking.closed_dates" deve avere giorni esistenti nel formato AAAA-MM-GG, come "2026-12-25" (trovato: "25/12/2026")'];
        yield 'chiusura inesistente' => [['closed_dates' => ['2026-02-30']], '"booking.closed_dates" deve avere giorni esistenti nel formato AAAA-MM-GG'];
        yield 'orario mancante' => [['schedule' => null], '"booking.schedule" deve essere un elenco giorno => orari, con tutti e sette i giorni'];
        yield 'giorno sconosciuto' => [['schedule' => [...$week, 'festivi' => []]], '"booking.schedule" ha un giorno sconosciuto: i giorni sono lunedì, martedì, mercoledì, giovedì, venerdì, sabato e domenica (trovato: "festivi")'];
        yield 'giorno ripetuto' => [['schedule' => [...$week, 'lunedi' => []]], '"booking.schedule" ha lunedì due volte (trovato: "lunedi")'];
        yield 'giorno mancante' => [['schedule' => array_diff_key($week, ['domenica' => true])], '"booking.schedule" deve elencare tutti e sette i giorni: manca domenica (scrivi [] se quel giorno è chiuso)'];
        yield 'orari non elenco' => [['schedule' => [...$week, 'lunedì' => '07:00']], '"booking.schedule.lunedì" deve essere un elenco di orari come [\'07:00\', \'18:30\'], oppure [] se il giorno è chiuso (trovato: "07:00")'];
        yield 'orario senza zero' => [['schedule' => [...$week, 'martedì' => ['7:00']]], '"booking.schedule.martedì" deve avere orari a 24 ore nel formato HH:MM, come "18:30" (trovato: "7:00")'];
        yield 'orario come numero' => [['schedule' => [...$week, 'martedì' => [7]]], '"booking.schedule.martedì" deve avere orari a 24 ore nel formato HH:MM, come "18:30" (trovato: "7")'];
        yield 'fascia ripetuta' => [['schedule' => [...$week, 'sabato' => ['18:30', '18:30']]], '"booking.schedule.sabato" ha la stessa fascia due volte (trovato: "18:30")'];
        yield 'fasce sovrapposte' => [['schedule' => [...$week, 'sabato' => ['18:30', '18:00']]], '"booking.schedule.sabato" ha due fasce che si sovrappongono, alle 18:00 e alle 18:30: ogni fascia dura 60 minuti (slot_minutes) (trovato: "18:30")'];
        yield 'fascia oltre la mezzanotte' => [['schedule' => [...$week, 'sabato' => ['23:30']]], '"booking.schedule.sabato" ha una fascia che finisce dopo la mezzanotte: ogni fascia dura 60 minuti (slot_minutes) (trovato: "23:30")'];
        yield 'fasce troppo vicine per la durata' => [['slot_minutes' => 90, 'schedule' => [...$week]], '"booking.schedule.lunedì" ha due fasce che si sovrappongono, alle 17:30 e alle 18:30: ogni fascia dura 90 minuti'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidRules')]
    public function testInvalidRulesStopTheSiteWithAClearMessage(array $overrides, string $message): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage($message);

        self::rules($overrides);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredKeys(): iterable
    {
        foreach (['online', 'capacity', 'schedule', 'slot_minutes', 'days_ahead', 'closed_dates', 'booking_closes_minutes_before', 'cancel_closes_minutes_before', 'max_upcoming', 'example_slots', 'default_slot'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('requiredKeys')]
    public function testEveryKeyIsRequired(string $key): void
    {
        $values = SiteFixture::booking();
        unset($values[$key]);

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('"booking.' . $key . '"');

        BookingRules::fromConfig(ConfigValues::root(['booking' => $values])->requiredSection('booking'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function rules(array $overrides = []): BookingRules
    {
        return BookingRules::fromConfig(ConfigValues::root(['booking' => SiteFixture::booking($overrides)])->requiredSection('booking'));
    }

    /**
     * @return list<string>
     */
    private static function times(BookingRules $rules, Weekday $day): array
    {
        return array_map(strval(...), $rules->schedule->on($day));
    }
}
