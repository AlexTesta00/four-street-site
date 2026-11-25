<?php

declare(strict_types=1);

namespace FourStreet\Http\Routing;

/**
 * Trova la rotta di una richiesta. Le rotte si provano nell'ordine in cui sono elencate.
 */
final readonly class Router
{
    /**
     * @param list<Route> $routes
     */
    public function __construct(
        private array $routes,
    ) {}

    public function match(string $method, string $path): RouteResult
    {
        $result = $this->find($method, $path);
        if (!$result instanceof RouteNotFound || str_ends_with($path, '/')) {
            return $result;
        }

        // Come faceva Apache con le cartelle: /links porta a /links/.
        $canonical = $this->find($method, $path . '/');
        if ($canonical instanceof RouteFound) {
            return new CanonicalRedirect(self::encodePath($path . '/'), in_array($method, ['GET', 'HEAD'], true) ? 301 : 308);
        }

        return $canonical instanceof MethodNotAllowed ? $canonical : $result;
    }

    private function find(string $method, string $path): RouteResult
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $parameters = $route->matchPath($path);
            if ($parameters === null) {
                continue;
            }
            if ($route->allows($method)) {
                return new RouteFound($route->controller, $parameters);
            }
            $allowed = [...$allowed, ...$route->methods];
        }

        return $allowed === [] ? new RouteNotFound() : new MethodNotAllowed(array_values(array_unique($allowed)));
    }

    private static function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
