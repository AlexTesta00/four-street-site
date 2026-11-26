<?php

declare(strict_types=1);

namespace FourStreet\Http\Middleware;

use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Un middleware davanti a un controller: insieme sono di nuovo un controller.
 * Route::through() li impila, il primo dell'elenco all'esterno.
 */
final readonly class Layer implements Controller
{
    public function __construct(
        private Middleware $middleware,
        private Controller $next,
    ) {}

    public function __invoke(Request $request): Response
    {
        return $this->middleware->process($request, $this->next);
    }
}
