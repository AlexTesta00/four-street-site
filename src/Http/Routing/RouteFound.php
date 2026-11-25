<?php

declare(strict_types=1);

namespace FourStreet\Http\Routing;

use FourStreet\Http\Controller;

final readonly class RouteFound implements RouteResult
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public Controller $controller,
        public array $parameters,
    ) {}
}
