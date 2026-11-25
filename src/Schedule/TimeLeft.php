<?php

declare(strict_types=1);

namespace FourStreet\Schedule;

/**
 * Tempo che manca in forma breve: un numero e la sua unità ("11 giorni", "1 ora").
 */
final readonly class TimeLeft
{
    public function __construct(
        public int $value,
        public string $unit,
    ) {}
}
