<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Booking\FirstFreeSeat;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slot::class)]
#[CoversClass(FirstFreeSeat::class)]
#[CoversClass(SlotTime::class)]
final class SlotTest extends TestCase
{
    /**
     * @return iterable<string, array{int, bool, int, int|null}>
     */
    public static function occupancy(): iterable
    {
        // posti presi su 5 => piena, posti liberi, posto di chi prenota adesso
        yield 'vuota' => [0, false, 5, 1];
        yield 'un posto preso' => [1, false, 4, 2];
        yield 'tre posti presi' => [3, false, 2, 4];
        yield 'un posto libero' => [4, false, 1, 5];
        yield 'piena' => [5, true, 0, null];
    }

    #[DataProvider('occupancy')]
    public function testAnExampleTakesTheFirstSeats(int $taken, bool $full, int $free, ?int $nextSeat): void
    {
        $slot = Slot::example('18:30', 5, $taken);

        self::assertSame('18:30', $slot->time);
        self::assertSame('18:30', $slot->startTime()->value);
        self::assertSame(5, $slot->capacity);
        self::assertSame($taken, $slot->taken);
        self::assertSame($taken, $slot->shownTaken());
        self::assertSame($full, $slot->isFull());
        self::assertSame(!$full, $slot->isBookable());
        self::assertSame($free, $slot->freeSeats());
        self::assertSame($nextSeat, $slot->nextSeat());
    }

    public function testASlotWithOneSeat(): void
    {
        self::assertSame(1, Slot::example('07:00', 1, 0)->nextSeat());
        self::assertTrue(Slot::example('07:00', 1, 1)->isFull());
    }

    public function testAFreedSeatIsTheNextOne(): void
    {
        // Il posto 2 è stato disdetto: chi prenota prende il primo libero, il 2, non il 5.
        $slot = new Slot('18:30', 5, [1, 3, 4]);

        self::assertSame(3, $slot->taken);
        self::assertSame(2, $slot->nextSeat());
        self::assertTrue($slot->isSeatTaken(3));
        self::assertFalse($slot->isSeatTaken(2));
        self::assertSame(2, (new FirstFreeSeat())->choose([1, 3, 4], 5));
    }

    public function testAClosedSlotOffersNoSeat(): void
    {
        $slot = new Slot('07:00', 5, [1], closed: true);

        self::assertFalse($slot->isFull());
        self::assertFalse($slot->isBookable());
        self::assertNull($slot->nextSeat());
        self::assertSame(4, $slot->freeSeats());
    }

    public function testAfterALowerCapacityTheSlotStaysFullUntilSeatsAreFreed(): void
    {
        // Capienza scesa da 5 a 4 con cinque prenotazioni: piena, e "4/4", mai "5/4".
        $full = new Slot('18:30', 4, [1, 2, 3, 4, 5]);
        self::assertTrue($full->isFull());
        self::assertSame(4, $full->shownTaken());
        self::assertSame(0, $full->freeSeats());
        self::assertNull($full->nextSeat());

        // Liberato il 3, i presenti sono quattro: ancora piena, anche se il posto 3 è vuoto.
        $stillFull = new Slot('18:30', 4, [1, 2, 4, 5]);
        self::assertTrue($stillFull->isFull());
        self::assertNull(FirstFreeSeat::among([1, 2, 4, 5], 4));

        // Liberato anche il 5: tre presenti, il prossimo è il 3.
        self::assertSame(3, (new Slot('18:30', 4, [1, 2, 4]))->nextSeat());
    }

    /**
     * @return iterable<string, array{string, int, list<int>, string}>
     */
    public static function invalidSlots(): iterable
    {
        yield 'ora senza zero' => ['7:00', 5, [], 'Orario di fascia non valido: "7:00"'];
        yield 'ora inesistente' => ['24:00', 5, [], 'Orario di fascia non valido'];
        yield 'minuti inesistenti' => ['18:60', 5, [], 'Orario di fascia non valido'];
        yield 'con i secondi' => ['18:30:00', 5, [], 'Orario di fascia non valido'];
        yield 'nessun posto' => ['18:30', 0, [], 'Una fascia ha almeno un posto: 0'];
        yield 'posto zero' => ['18:30', 5, [0], 'Numero di posto non valido: 0'];
        yield 'posto ripetuto' => ['18:30', 5, [2, 2], 'Un posto della fascia delle 18:30 è preso due volte.'];
    }

    /**
     * @param list<int> $seats
     */
    #[DataProvider('invalidSlots')]
    public function testRejectsImpossibleSlots(string $time, int $capacity, array $seats, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Slot($time, $capacity, $seats);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function invalidExamples(): iterable
    {
        yield 'posti presi negativi' => [-1, 'Posti occupati fuori dai limiti: -1 su 5'];
        yield 'più posti presi che posti' => [6, 'Posti occupati fuori dai limiti: 6 su 5'];
    }

    #[DataProvider('invalidExamples')]
    public function testRejectsImpossibleExamples(int $taken, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Slot::example('18:30', 5, $taken);
    }
}
