<?php

declare(strict_types=1);

namespace FourStreet\Http\Middleware;

use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Pagine con moduli o dati personali: nessuna copia in cache (né nel browser né in un proxy)
 * e nessun motore di ricerca le indicizza. Vale per ogni risposta della rotta, errori compresi.
 */
final readonly class PrivatePage implements Middleware
{
    public function process(Request $request, Controller $next): Response
    {
        return $next($request)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex');
    }
}
