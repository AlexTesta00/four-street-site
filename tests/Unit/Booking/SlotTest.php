<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Booking\Slot;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slot::class)]
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
    public function testDerivesTheSeatsFromCapacityAndTaken(int $taken, bool $full, int $free, ?int $nextSeat): void
    {
        $slot = new Slot('18:30', 5, $taken);

        self::assertSame('18:30', $slot->time);
        self::assertSame(5, $slot->capacity);
        self::assertSame($taken, $slot->taken);
        self::assertSame($full, $slot->isFull());
        self::assertSame($free, $slot->freeSeats());
        self::assertSame($nextSeat, $slot->nextSeat());
    }

    public function testASlotWithOneSeat(): void
    {
        self::assertSame(1, (new Slot('07:00', 1, 0))->nextSeat());
        self::assertTrue((new Slot('07:00', 1, 1))->isFull());
    }

    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function invalidSlots(): iterable
    {
        yield 'ora senza zero' => ['7:00', 5, 0, 'Orario di fascia non valido: "7:00"'];
        yield 'ora inesistente' => ['24:00', 5, 0, 'Orario di fascia non valido'];
        yield 'minuti inesistenti' => ['18:60', 5, 0, 'Orario di fascia non valido'];
        yield 'con i secondi' => ['18:30:00', 5, 0, 'Orario di fascia non valido'];
        yield 'nessun posto' => ['18:30', 0, 0, 'Una fascia ha almeno un posto: 0'];
        yield 'posti presi negativi' => ['18:30', 5, -1, 'Posti occupati fuori dai limiti: -1 su 5'];
        yield 'più posti presi che posti' => ['18:30', 5, 6, 'Posti occupati fuori dai limiti: 6 su 5'];
    }

    #[DataProvider('invalidSlots')]
    public function testRejectsImpossibleSlots(string $time, int $capacity, int $taken, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Slot($time, $capacity, $taken);
    }
}
