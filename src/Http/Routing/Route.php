<?php

declare(strict_types=1);

namespace FourStreet\Http\Routing;

use FourStreet\Http\Controller;
use FourStreet\Http\Middleware\Layer;
use FourStreet\Http\Middleware\Middleware;
use InvalidArgumentException;

/**
 * Una rotta: metodi ammessi, percorso e controller.
 * Il percorso è esatto ("/links/") oppure con parametri tra graffe ("/corsi/{slug}"), uno per segmento.
 */
final readonly class Route
{
    private const string PARAMETER_PATTERN = '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/';

    /**
     * @param non-empty-list<string> $methods
     * @param list<string> $parameters
     */
    private function __construct(
        public array $methods,
        public string $pattern,
        public Controller $controller,
        private ?string $regex,
        private array $parameters,
    ) {}

    /**
     * Rotta per GET (e quindi anche HEAD).
     */
    public static function get(string $pattern, Controller $controller): self
    {
        return self::create(['GET'], $pattern, $controller);
    }

    /**
     * Rotta per POST soltanto (un'azione, per esempio l'uscita dall'account).
     */
    public static function post(string $pattern, Controller $controller): self
    {
        return self::create(['POST'], $pattern, $controller);
    }

    /**
     * Pagina con un modulo: GET (e HEAD) lo mostra, POST lo riceve.
     */
    public static function form(string $pattern, Controller $controller): self
    {
        return self::create(['GET', 'POST'], $pattern, $controller);
    }

    /**
     * @param non-empty-list<string> $methods
     */
    public static function create(array $methods, string $pattern, Controller $controller): self
    {
        if (!str_starts_with($pattern, '/')) {
            throw new InvalidArgumentException(sprintf('Il percorso di una rotta inizia con "/": "%s".', $pattern));
        }

        $methods = array_values(array_unique(array_map(strtoupper(...), $methods)));
        // Chi risponde a GET risponde anche a HEAD (stesse intestazioni, senza corpo).
        if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }

        [$regex, $parameters] = self::compile($pattern);

        return new self($methods, $pattern, $controller, $regex, $parameters);
    }

    /**
     * La stessa rotta con dei middleware davanti al controller. Il primo dell'elenco riceve la richiesta
     * per primo (e la risposta per ultimo).
     */
    public function through(Middleware ...$middleware): self
    {
        $controller = $this->controller;
        foreach (array_reverse($middleware) as $layer) {
            $controller = new Layer($layer, $controller);
        }

        return new self($this->methods, $this->pattern, $controller, $this->regex, $this->parameters);
    }

    public function allows(string $method): bool
    {
        return in_array($method, $this->methods, true);
    }

    /**
     * Parametri estratti dal percorso, oppure null se il percorso non corrisponde.
     *
     * @return array<string, string>|null
     */
    public function matchPath(string $path): ?array
    {
        if ($this->regex === null) {
            return $path === $this->pattern ? [] : null;
        }
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $values = [];
        foreach ($this->parameters as $parameter) {
            $values[$parameter] = $matches[$parameter] ?? '';
        }

        return $values;
    }

    /**
     * @return array{0: string|null, 1: list<string>}
     */
    private static function compile(string $pattern): array
    {
        if (!str_contains($pattern, '{')) {
            return [null, []];
        }

        $parts = preg_split(self::PARAMETER_PATTERN, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new InvalidArgumentException(sprintf('Percorso di rotta non valido: "%s".', $pattern));
        }

        $regex = '';
        $parameters = [];
        foreach ($parts as $index => $part) {
            // Con DELIM_CAPTURE le posizioni dispari sono i nomi dei parametri.
            if ($index % 2 === 0) {
                if (str_contains($part, '{') || str_contains($part, '}')) {
                    throw new InvalidArgumentException(sprintf('Parametro di rotta non valido in "%s".', $pattern));
                }
                $regex .= preg_quote($part, '#');

                continue;
            }
            if (in_array($part, $parameters, true)) {
                throw new InvalidArgumentException(sprintf('Parametro "%s" ripetuto in "%s".', $part, $pattern));
            }
            $parameters[] = $part;
            $regex .= '(?P<' . $part . '>[^/]+)';
        }

        return ['#\A' . $regex . '\z#', $parameters];
    }
}
