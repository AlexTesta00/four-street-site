<?php

declare(strict_types=1);

namespace FourStreet\Controller;

use FourStreet\Booking\Landing\SlotPreviewSource;
use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Maps\MapLinks;
use FourStreet\Schedule\OpeningSchedule;
use FourStreet\Schedule\Preview;
use FourStreet\Seo\GymStructuredData;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\View;

/**
 * Home: la landing di 4Street. Prima dell'inaugurazione ha il countdown; dal giorno dell'apertura
 * passa allo stato "aperti" (allo zero lo script fa lo stesso passaggio senza ricaricare la pagina).
 * La sezione delle fasce viene da $slots: con le prenotazioni attive mostra i posti veri, e allora
 * la pagina non va in cache (Cache-Control: no-cache, il browser la richiede ogni volta).
 * Non legge mai la sessione: è uguale per tutti.
 */
final readonly class HomeController implements Controller
{
    /**
     * @param string|null $accountUrl l'area clienti, se il server la offre: un link fisso, uguale per tutti
     */
    public function __construct(
        private View $view,
        private SiteConfig $site,
        private Clock $clock,
        private ItalianDateFormatter $dates,
        private MapLinks $maps,
        private SlotPreviewSource $slots,
        private ?string $accountUrl = null,
    ) {}

    public function __invoke(Request $request): Response
    {
        $opening = $this->site->opening;
        $now = $this->clock->now();
        $preview = Preview::fromQuery($request->queryParam('anteprima'));

        $response = Response::html($this->view->render('pages/home', [
            'site' => $this->site,
            'schedule' => new OpeningSchedule($opening, $now, $preview),
            'maps' => $this->maps,
            'slots' => $this->slots->preview(),
            'accountUrl' => $this->accountUrl,
            'content' => $this->site->content,
            'structuredData' => GymStructuredData::fromSite($this->site)->script(),
            'dateLong' => $this->dates->format($opening),
            'dateText' => $this->dates->format($opening, capitalize: false),
            'dateShort' => $this->dates->format($opening, withYear: false, capitalize: false),
            'dateTag' => $opening->format('d.m.Y'),
            'year' => $now->format('Y'),
        ]));

        // I posti cambiano a ogni prenotazione: una copia in cache mostrerebbe numeri vecchi.
        return $this->slots->isLive() ? $response->withHeader('Cache-Control', 'no-cache') : $response;
    }
}
