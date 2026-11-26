<?php

declare(strict_types=1);

namespace FourStreet\Http\Middleware;

use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Pagine con un token nell'indirizzo (link delle email): il browser non manda l'indirizzo come Referer
 * a nessuno, nemmeno ai fogli di stile e ai font, così il token non esce dalla pagina.
 */
final readonly class NoReferrer implements Middleware
{
    public function process(Request $request, Controller $next): Response
    {
        return $next($request)->withHeader('Referrer-Policy', 'no-referrer');
    }
}
