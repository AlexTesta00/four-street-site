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
     * @param string $remoteAddress indirizzo IP del client (REMOTE_ADDR), vuoto se non si conosce
     * @param array<array-key, mixed> $query parametri della query string, come in $_GET
     * @param array<string, string> $headers intestazioni con il nome in minuscolo
     * @param array<array-key, mixed> $body campi del modulo inviato in POST, come in $_POST
     * @param array<string, string> $cookies
     * @param array<string, string> $attributes parametri estratti dalla rotta
     * @param array<class-string, object> $context oggetti che i middleware passano ai controller, uno per classe
     */
    private function __construct(
        public string $method,
        public string $path,
        public string $queryString,
        public string $remoteAddress,
        private array $query,
        private array $headers,
        private array $body,
        private array $cookies,
        private array $attributes,
        private array $context,
    ) {}

    /**
     * @param string $uri percorso con l'eventuale query string, come in REQUEST_URI
     * @param array<string, string> $headers
     * @param array<array-key, mixed> $body campi del modulo, come in $_POST
     * @param array<array-key, mixed> $cookies come in $_COOKIE
     */
    public static function create(
        string $method,
        string $uri,
        array $headers = [],
        array $body = [],
        array $cookies = [],
        string $remoteAddress = '',
    ): self {
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

        // Solo i cookie con nome e valore testuali: PHP trasforma "a[b]=1" in un array.
        $textCookies = [];
        foreach ($cookies as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $textCookies[$name] = $value;
            }
        }

        return new self(strtoupper($method), $path, $queryString, $remoteAddress, $query, $normalizedHeaders, $body, $textCookies, [], []);
    }

    /**
     * @param array<array-key, mixed> $server $_SERVER
     * @param array<array-key, mixed> $post $_POST
     * @param array<array-key, mixed> $cookies $_COOKIE
     */
    public static function fromGlobals(array $server, array $post = [], array $cookies = []): self
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
        $remoteAddress = $server['REMOTE_ADDR'] ?? null;

        return self::create(
            is_string($method) ? $method : 'GET',
            is_string($uri) ? $uri : '/',
            $headers,
            $post,
            $cookies,
            is_string($remoteAddress) ? $remoteAddress : '',
        );
    }

    /**
     * Valore testuale di un parametro della query string (gli array sono ignorati).
     */
    public function queryParam(string $name): ?string
    {
        $value = $this->query[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Valore testuale di un campo del modulo inviato (gli array sono ignorati).
     */
    public function bodyParam(string $name): ?string
    {
        $value = $this->body[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Parametro estratto dalla rotta, per esempio {slug}.
     */
    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * La richiesta è di sola lettura (GET, HEAD, OPTIONS): non deve cambiare nulla sul server.
     */
    public function isSafe(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
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
            $this->remoteAddress,
            $this->query,
            $this->headers,
            $this->body,
            $this->cookies,
            [...$this->attributes, ...$attributes],
            $this->context,
        );
    }

    /**
     * La stessa richiesta con un oggetto di contesto in più, ritrovabile per classe con context().
     * È così che un middleware passa al controller ciò che ha trovato (il visitatore, il token CSRF).
     */
    public function withContext(object $value): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->queryString,
            $this->remoteAddress,
            $this->query,
            $this->headers,
            $this->body,
            $this->cookies,
            $this->attributes,
            [...$this->context, $value::class => $value],
        );
    }

    /**
     * L'oggetto di contesto di quella classe, oppure null se nessun middleware l'ha aggiunto.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function context(string $class): ?object
    {
        $value = $this->context[$class] ?? null;

        return $value instanceof $class ? $value : null;
    }

    /**
     * Percorso indicato con la query string di questa richiesta (per i redirect).
     */
    public function pathWithQuery(string $path): string
    {
        return $this->queryString === '' ? $path : $path . '?' . $this->queryString;
    }
}
