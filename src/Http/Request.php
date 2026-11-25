<?php

declare(strict_types=1);

namespace FourStreet\Http;

/**
 * Richiesta HTTP immutabile. Dai superglobali si costruisce in un punto solo: public/index.php.
 */
final readonly class Request
{
    /**
     * @param string $path percorso decodificato, sempre con la barra iniziale
     * @param string $queryString query string grezza, senza "?"
     * @param array<array-key, mixed> $query parametri della query string, come in $_GET
     * @param array<string, string> $headers intestazioni con il nome in minuscolo
     * @param array<string, string> $attributes parametri estratti dalla rotta
     */
    private function __construct(
        public string $method,
        public string $path,
        public string $queryString,
        private array $query,
        private array $headers,
        private array $attributes,
    ) {}

    /**
     * @param string $uri percorso con l'eventuale query string, come in REQUEST_URI
     * @param array<string, string> $headers
     */
    public static function create(string $method, string $uri, array $headers = []): self
    {
        [$target] = explode('#', $uri, 2);
        $parts = explode('?', $target, 2);
        $path = $parts[0];
        $queryString = $parts[1] ?? '';

        // Forma assoluta (http://host/percorso): conta solo il percorso.
        if (preg_match('#\A[a-z][a-z0-9+.-]*://[^/]*#i', $path, $authority) === 1) {
            $path = substr($path, strlen($authority[0]));
        }
        // Come Apache: le barre ripetute valgono una.
        $path = '/' . ltrim((string) preg_replace('#/{2,}#', '/', rawurldecode($path)), '/');

        parse_str($queryString, $query);

        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = $value;
        }

        return new self(strtoupper($method), $path, $queryString, $query, $normalizedHeaders, []);
    }

    /**
     * @param array<array-key, mixed> $server $_SERVER
     */
    public static function fromGlobals(array $server): self
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[str_replace('_', '-', $key)] = $value;
            }
        }

        $method = $server['REQUEST_METHOD'] ?? null;
        $uri = $server['REQUEST_URI'] ?? null;

        return self::create(is_string($method) ? $method : 'GET', is_string($uri) ? $uri : '/', $headers);
    }

    /**
     * Valore testuale di un parametro della query string (gli array sono ignorati).
     */
    public function queryParam(string $name): ?string
    {
        $value = $this->query[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Parametro estratto dalla rotta, per esempio {slug}.
     */
    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->queryString,
            $this->query,
            $this->headers,
            [...$this->attributes, ...$attributes],
        );
    }

    /**
     * Percorso indicato con la query string di questa richiesta (per i redirect).
     */
    public function pathWithQuery(string $path): string
    {
        return $this->queryString === '' ? $path : $path . '?' . $this->queryString;
    }
}
