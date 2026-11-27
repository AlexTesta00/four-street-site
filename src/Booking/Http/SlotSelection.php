<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Http\Request;

/**
 * La fascia scelta, dai parametri "giorno" e "fascia" (dell'indirizzo o del modulo). Un parametro che manca
 * vale null; uno scritto male (un giorno che non esiste, "7:00", testo qualunque) rende la scelta manomessa.
 */
final readonly class SlotSelection
{
    private function __construct(
        public ?LocalDate $date,
        public ?SlotTime $time,
        public bool $tampered,
    ) {}

    public static function fromQuery(Request $request): self
    {
        return self::parse($request->queryParam(BookingUrls::DAY_PARAMETER), $request->queryParam(BookingUrls::TIME_PARAMETER));
    }

    public static function fromBody(Request $request): self
    {
        return self::parse($request->bodyParam(BookingUrls::DAY_PARAMETER), $request->bodyParam(BookingUrls::TIME_PARAMETER));
    }

    private static function parse(?string $day, ?string $time): self
    {
        $date = $day === null ? null : LocalDate::tryFrom($day);
        $slotTime = $time === null ? null : SlotTime::tryFrom($time);

        return new self($date, $slotTime, ($day !== null && $date === null) || ($time !== null && $slotTime === null));
    }
}
