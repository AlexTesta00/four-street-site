<?php

declare(strict_types=1);

namespace FourStreet\Controller;

use FourStreet\Calendar\IcsCalendar;
use FourStreet\Calendar\IcsEvent;
use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * /calendario.php: l'inaugurazione da aggiungere al calendario (.ics). L'indirizzo è già stato condiviso.
 */
final readonly class CalendarController implements Controller
{
    public function __construct(
        private SiteConfig $site,
        private Clock $clock,
    ) {}

    public function __invoke(Request $request): Response
    {
        $opening = $this->site->opening;
        $calendar = (new IcsCalendar('-//4Street//Inaugurazione//IT'))->withEvent(new IcsEvent(
            uid: 'inaugurazione-' . $opening->format('Ymd') . '@4street',
            summary: 'Inaugurazione 4Street',
            location: $this->site->address,
            start: $opening,
            allDay: !$this->site->openingTimeConfirmed,
            stamp: $this->clock->now(),
        ));

        return new Response(200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="inaugurazione-4street.ics"',
            'Cache-Control' => 'no-store',
        ], $calendar->render());
    }
}
