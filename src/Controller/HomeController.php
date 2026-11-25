<?php

declare(strict_types=1);

namespace FourStreet\Controller;

use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Maps\MapLinks;
use FourStreet\Schedule\OpeningSchedule;
use FourStreet\Schedule\Preview;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\View;

/**
 * Home: countdown fino all'inaugurazione, poi la mappa ("Ci siamo." e, dal giorno dopo, "Siamo aperti.").
 */
final readonly class HomeController implements Controller
{
    public function __construct(
        private View $view,
        private SiteConfig $site,
        private Clock $clock,
        private ItalianDateFormatter $dates,
        private MapLinks $maps,
    ) {}

    public function __invoke(Request $request): Response
    {
        $opening = $this->site->opening;
        $preview = Preview::fromQuery($request->queryParam('anteprima'));

        return Response::html($this->view->render('pages/home', [
            'site' => $this->site,
            'schedule' => new OpeningSchedule($opening, $this->clock->now(), $preview),
            'maps' => $this->maps,
            'dateLong' => $this->dates->format($opening),
            'dateShort' => $this->dates->format($opening, withYear: false, capitalize: false),
            'dateTag' => $opening->format('d.m.Y'),
        ]));
    }
}
