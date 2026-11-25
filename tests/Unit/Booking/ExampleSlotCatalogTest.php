<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotCatalog;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\InvalidSiteConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleSlotCatalog::class)]
#[UsesClass(Slot::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(InvalidSiteConfig::class)]
final class ExampleSlotCatalogTest extends TestCase
{
    private const array BOOKING = [
        'capacity' => 5,
        'example_slots' => ['07:00' => 2, '09:30' => 4, '12:30' => 1, '17:30' => 5, '18:30' => 3, '20:00' => 0],
        'default_slot' => '18:30',
    ];

    public function testServesTheExampleSlotsInTheirOrder(): void
    {
        $catalog = self::catalog(ExampleSlotCatalog::fromConfig(ConfigValues::root(self::BOOKING)));

        $taken = [];
        foreach ($catalog->slots() as $slot) {
            $taken[$slot->time] = $slot->taken;
            self::assertSame(5, $slot->capacity);
        }
        self::assertSame(5, $catalog->capacity());
        self::assertSame(['07:00' => 2, '09:30' => 4, '12:30' => 1, '17:30' => 5, '18:30' => 3, '20:00' => 0], $taken);
    }

    public function testTheDefaultSlot(): void
    {
        $default = ExampleSlotCatalog::fromConfig(ConfigValues::root(self::BOOKING))->defaultSlot();

        self::assertSame('18:30', $default->time);
        self::assertSame(4, $default->nextSeat());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidBookings(): iterable
    {
        yield 'chiave sconosciuta' => [['slots' => []], 'chiavi sconosciute (slots)'];
        yield 'capienza zero' => [['capacity' => 0], '"capacity" deve essere un numero intero maggiore di zero (trovato: "0")'];
        yield 'capienza come testo' => [['capacity' => '5'], '"capacity" deve essere un numero intero maggiore di zero'];
        yield 'nessuna fascia' => [['example_slots' => []], '"example_slots" deve essere un elenco orario => posti occupati, con almeno una fascia'];
        yield 'fasce non elenco' => [['example_slots' => '18:30'], '"example_slots" deve essere un elenco'];
        yield 'orario senza zero' => [['example_slots' => ['7:00' => 1]], '"example_slots" deve avere orari a 24 ore nel formato HH:MM, come "18:30" (trovato: "7:00")'];
        yield 'orario come numero' => [['example_slots' => [7 => 1]], '"example_slots" deve avere orari a 24 ore nel formato HH:MM, come "18:30" (trovato: "7")'];
        yield 'posti presi negativi' => [['example_slots' => ['18:30' => -1]], '"example_slots.18:30" deve essere il numero di posti occupati, da 0 a 5 (trovato: "-1")'];
        yield 'più posti presi che posti' => [['example_slots' => ['18:30' => 6]], '"example_slots.18:30" deve essere il numero di posti occupati, da 0 a 5 (trovato: "6")'];
        yield 'posti presi come testo' => [['example_slots' => ['18:30' => '3']], '"example_slots.18:30" deve essere il numero di posti occupati, da 0 a 5 (trovato: "3")'];
        yield 'fascia scelta mancante' => [['default_slot' => ''], '"default_slot" deve essere un testo non vuoto'];
        yield 'fascia scelta inesistente' => [['default_slot' => '19:00'], '"default_slot" deve essere l\'orario di una fascia di esempio non piena (trovato: "19:00")'];
        yield 'fascia scelta piena' => [['default_slot' => '17:30'], '"default_slot" deve essere l\'orario di una fascia di esempio non piena (trovato: "17:30")'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidBookings')]
    public function testInvalidBookingsStopTheSite(array $overrides, string $message): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage($message);

        ExampleSlotCatalog::fromConfig(ConfigValues::root([...self::BOOKING, ...$overrides]));
    }

    /**
     * @return iterable<string, array{non-empty-list<Slot>, string, string}>
     */
    public static function brokenInvariants(): iterable
    {
        yield 'capienza diversa' => [[new Slot('18:30', 4, 1)], '18:30', 'La fascia delle 18:30 non ha 5 posti.'];
        yield 'fascia scelta inesistente' => [[new Slot('18:30', 5, 1)], '19:00', 'La fascia scelta deve esistere e non essere piena: "19:00".'];
        yield 'fascia scelta piena' => [[new Slot('18:30', 5, 5)], '18:30', 'La fascia scelta deve esistere e non essere piena: "18:30".'];
    }

    /**
     * @param non-empty-list<Slot> $slots
     */
    #[DataProvider('brokenInvariants')]
    public function testTheCatalogItselfRejectsInconsistentSlots(array $slots, string $defaultTime, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new ExampleSlotCatalog(5, $slots, $defaultTime);
    }

    /**
     * Il catalogo come lo vedono il controller e i template: solo l'interfaccia.
     */
    private static function catalog(SlotCatalog $catalog): SlotCatalog
    {
        return $catalog;
    }
}
