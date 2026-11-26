<?php

declare(strict_types=1);

namespace FourStreet\Http\Middleware;

use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Un passaggio davanti al controller di una rotta: può rispondere da sé (accesso negato, modulo scaduto)
 * oppure passare la richiesta, anche arricchita, a $next e ritoccarne la risposta (intestazioni, cookie).
 * Si collega a una rotta con Route::through().
 */
interface Middleware
{
    public function process(Request $request, Controller $next): Response;
}
