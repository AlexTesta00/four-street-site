<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use FourStreet\Http\Controller;
use FourStreet\Http\Middleware\Middleware;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Riconosce il visitatore dal cookie di sessione e lo passa al controller (Visitor::of($request)).
 * Sulla via del ritorno rimanda il cookie quando la sessione si è allungata, o lo cancella se non vale più,
 * a meno che il controller non abbia già deciso lui del cookie (accesso, uscita).
 */
final readonly class StartSession implements Middleware
{
    public function __construct(
        private SessionManager $sessions,
    ) {}

    public function process(Request $request, Controller $next): Response
    {
        $state = $this->sessions->resolve($request);
        $response = $next($request->withContext($state->visitor));

        if ($state->cookie === null || $response->setsCookie($state->cookie->name)) {
            return $response;
        }

        return $response->withCookie($state->cookie);
    }
}
