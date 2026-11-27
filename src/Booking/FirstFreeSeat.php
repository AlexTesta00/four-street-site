<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Il primo posto libero: con 1, 3 e 4 presi tocca al 2. La fascia è piena quando le prenotazioni attive
 * sono tante quanti i posti, anche se qualcuna (dopo una capienza abbassata) ha un numero più alto.
 * Così due prenotazioni lette insieme scelgono lo stesso posto, e il vincolo del database ne fa passare una.
 */
final readonly class FirstFreeSeat implements SeatChooser
{
    public function choose(array $taken, int $capacity): ?int
    {
        return self::among($taken, $capacity);
    }

    /**
     * @param list<int> $taken
     */
    public static function among(array $taken, int $capacity): ?int
    {
        if (count($taken) >= $capacity) {
            return null;
        }
        for ($seat = 1; $seat <= $capacity; ++$seat) {
            if (!in_array($seat, $taken, true)) {
                return $seat;
            }
        }

        return null;
    }
}
