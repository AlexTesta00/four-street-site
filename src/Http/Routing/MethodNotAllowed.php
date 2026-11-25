<?php

declare(strict_types=1);

namespace FourStreet\Http\Routing;

/**
 * Il percorso esiste, ma non con questo metodo: la risposta è 405 con l'intestazione Allow.
 */
final readonly class MethodNotAllowed implements RouteResult
{
    /**
     * @param non-empty-list<string> $allowedMethods
     */
    public function __construct(
        public array $allowedMethods,
    ) {}
}
