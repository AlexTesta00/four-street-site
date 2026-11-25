<?php

declare(strict_types=1);

namespace FourStreet\Controller;

use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Rimanda a un altro percorso del sito, conservando la query string (anteprime comprese).
 */
final readonly class RedirectController implements Controller
{
    public function __construct(
        private string $target,
        private int $status = 301,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Response::redirect($request->pathWithQuery($this->target), $this->status);
    }
}
