<?php

declare(strict_types=1);

namespace FourStreet\Http\Routing;

/**
 * Il percorso esiste con la barra finale: si rimanda all'indirizzo canonico (/links → /links/).
 */
final readonly class CanonicalRedirect implements RouteResult
{
    /**
     * @param string $location percorso canonico, già codificato per un URL
     * @param int $status 301 per GET e HEAD, 308 per gli altri metodi (che così restano invariati)
     */
    public function __construct(
        public string $location,
        public int $status,
    ) {}
}
