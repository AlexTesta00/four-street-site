<?php

declare(strict_types=1);

namespace FourStreet\Controller;

use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\View\View;

/**
 * Un modulo respinto dalla protezione CSRF (403): token mancante o diverso, oppure inviato da un altro sito.
 * Di solito è solo una pagina rimasta aperta a lungo: la pagina propone di ricaricarla e riprovare.
 */
final readonly class FormExpiredController implements Controller
{
    public function __construct(
        private View $view,
        private SiteConfig $site,
        private Clock $clock,
    ) {}

    public function __invoke(Request $request): Response
    {
        $path = implode('/', array_map(rawurlencode(...), explode('/', $request->path)));

        return Response::html($this->view->render('pages/form-expired', [
            'site' => $this->site,
            'year' => $this->clock->now()->format('Y'),
            'retryUrl' => $request->pathWithQuery($path),
        ]), 403);
    }
}
