<?php

declare(strict_types=1);

namespace FourStreet\Http;

/**
 * Gestisce le richieste di una rotta.
 */
interface Controller
{
    public function __invoke(Request $request): Response;
}
