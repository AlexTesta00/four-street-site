<?php

declare(strict_types=1);

namespace FourStreet\Http\Routing;

/**
 * Un modulo del sito che porta le sue rotte (l'area clienti, le prenotazioni): Application le mette
 * nel router senza conoscerle una per una.
 */
interface RouteProvider
{
    /**
     * @return list<Route>
     */
    public function routes(): array;
}
