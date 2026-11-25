<?php

declare(strict_types=1);

namespace FourStreet\Controller;

use FourStreet\Config\SiteConfig;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\View\View;

/**
 * Pagina 404 con la grafica del sito.
 */
final readonly class NotFoundController implements Controller
{
    public function __construct(
        private View $view,
        private SiteConfig $site,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Response::html($this->view->render('pages/not-found', [
            'site' => $this->site,
            'dateTag' => $this->site->opening->format('d.m.Y'),
        ]), 404);
    }
}
