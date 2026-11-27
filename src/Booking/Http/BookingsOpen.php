<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Booking\BookingCalendar;
use FourStreet\Http\Controller;
use FourStreet\Http\Middleware\Middleware;
use FourStreet\Http\Middleware\PrivatePage;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Davanti a /prenota/ e al riepilogo, quando nel file le prenotazioni dal sito sono accese: se il titolare le ha spente
 * dal pannello, la pagina "Prenotazioni presto" (503, come con booking.online a false), prima ancora di riconoscere
 * il visitatore o di chiedergli l'accesso. Con booking.online a false nel file queste rotte sono già quelle "presto",
 * e nessuna pagina pubblica legge il database.
 */
final readonly class BookingsOpen implements Middleware
{
    /**
     * @param Controller $comingSoon la pagina "Prenotazioni presto"
     */
    public function __construct(
        private BookingCalendar $calendar,
        private Controller $comingSoon,
    ) {}

    public function process(Request $request, Controller $next): Response
    {
        return $this->calendar->rules()->online ? $next($request) : (new PrivatePage())->process($request, $this->comingSoon);
    }
}
