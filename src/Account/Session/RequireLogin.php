<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\ReturnPath;
use FourStreet\Http\Controller;
use FourStreet\Http\Middleware\Middleware;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Pagine riservate a chi ha fatto l'accesso. Gli altri vanno alla pagina di accesso e, dopo, tornano qui.
 * Va dopo StartSession, che riconosce il visitatore.
 */
final readonly class RequireLogin implements Middleware
{
    public function process(Request $request, Controller $next): Response
    {
        if (Visitor::of($request)->isLoggedIn()) {
            return $next($request);
        }

        // Si torna solo a una pagina da leggere: rifare un POST dopo l'accesso non avrebbe senso.
        $returnTo = $request->isSafe() ? ReturnPath::fromRequest($request) : ReturnPath::default();

        return Response::seeOther(AccountUrls::to(AccountUrls::LOGIN, $returnTo));
    }
}
