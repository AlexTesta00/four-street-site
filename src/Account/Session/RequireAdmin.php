<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use FourStreet\Account\Role;
use FourStreet\Http\Controller;
use FourStreet\Http\Middleware\Middleware;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Pagine riservate al titolare (ruolo admin). Va dopo RequireLogin, che manda gli ospiti alla pagina di accesso.
 * Per chi è entrato ma non è titolare la pagina non esiste: la stessa pagina 404 di un indirizzo sconosciuto,
 * senza dire che lì c'è qualcosa di riservato. Il ruolo si legge dal database a ogni richiesta (StartSession):
 * toglierlo vale dalla pagina successiva.
 */
final readonly class RequireAdmin implements Middleware
{
    /**
     * @param Controller $notFound la pagina degli indirizzi che non esistono
     */
    public function __construct(
        private Controller $notFound,
    ) {}

    public function process(Request $request, Controller $next): Response
    {
        return Visitor::of($request)->user?->role === Role::Admin ? $next($request) : ($this->notFound)($request);
    }
}
