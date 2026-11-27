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
 * Pagina /links/: tutti i link di 4Street, con l'inaugurazione in evidenza. Non legge mai il database.
 */
final readonly class LinksController implements Controller
{
    /**
     * @param string|null $bookingUrl la pagina delle prenotazioni, se sono attive; altrimenti la voce dice "Presto"
     */
    public function __construct(
        private View $view,
        private SiteConfig $site,
        private Clock $clock,
        private ItalianDateFormatter $dates,
        private MapLinks $maps,
        private ?string $bookingUrl = null,
    ) {}

    public function __invoke(Request $request): Response
    {
        $opening = $this->site->opening;
        $preview = Preview::fromQuery($request->queryParam('anteprima'));

        return Response::html($this->view->render('pages/links', [
            'site' => $this->site,
            'schedule' => new OpeningSchedule($opening, $this->clock->now(), $preview),
            'maps' => $this->maps,
            'dateLong' => $this->dates->format($opening),
            'bookingUrl' => $this->bookingUrl,
        ]));
    }
}
