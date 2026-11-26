<?php

declare(strict_types=1);

namespace FourStreet\Http;

use InvalidArgumentException;
use Stringable;

/**
 * Risposta HTTP immutabile: stato, intestazioni e corpo. send() la consegna al client.
 */
final readonly class Response
{
    private const string TOKEN_PATTERN = "/\\A[!#$%&'*+.^_`|~0-9A-Za-z-]+\\z/";

    /** @var array<lowercase-string, array{name: string, values: non-empty-list<string>}> */
    private array $headers;

    /**
     * @param array<string, string|non-empty-list<string>> $headers nome => valore (o più valori)
     */
    public function __construct(
        public int $status = 200,
        array $headers = [],
        public string $body = '',
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException(sprintf('Stato HTTP non valido: %d.', $status));
        }

        $normalized = [];
        foreach ($headers as $name => $values) {
            $values = is_string($values) ? [$values] : $values;
            foreach ($values as $value) {
                self::assertValidHeader($name, $value);
            }
            $normalized[strtolower($name)] = ['name' => $name, 'values' => $values];
        }
        $this->headers = $normalized;
    }

    public static function html(string|Stringable $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], (string) $body);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    public static function redirect(string $location, int $status = 301): self
    {
        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException(sprintf('Stato non valido per un redirect: %d.', $status));
        }

        return new self($status, ['Location' => $location]);
    }

    /**
     * 303 See Other: dopo un modulo inviato con successo il browser apre la pagina con GET,
     * così ricaricare non invia di nuovo i dati.
     */
    public static function seeOther(string $location): self
    {
        return self::redirect($location, 303);
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Valore dell'intestazione (più valori separati da virgola), oppure null.
     */
    public function header(string $name): ?string
    {
        $header = $this->headers[strtolower($name)] ?? null;

        return $header === null ? null : implode(', ', $header['values']);
    }

    /**
     * @return array<string, non-empty-list<string>> nome => valori
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($this->headers as $header) {
            $headers[$header['name']] = $header['values'];
        }

        return $headers;
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, [...$this->headersExcept($name), $name => $value], $this->body);
    }

    /**
     * Aggiunge un valore senza togliere quelli presenti (per esempio più Set-Cookie).
     */
    public function withAddedHeader(string $name, string $value): self
    {
        $current = $this->headers[strtolower($name)] ?? null;
        if ($current === null) {
            return $this->withHeader($name, $value);
        }

        return new self(
            $this->status,
            [...$this->headersExcept($name), $current['name'] => [...$current['values'], $value]],
            $this->body,
        );
    }

    /**
     * Aggiunge un Set-Cookie, senza togliere quelli già presenti.
     */
    public function withCookie(Cookie $cookie): self
    {
        return $this->withAddedHeader('Set-Cookie', $cookie->headerValue());
    }

    /**
     * La risposta imposta già il cookie con quel nome (per crearlo, rinnovarlo o cancellarlo).
     */
    public function setsCookie(string $name): bool
    {
        $header = $this->headers['set-cookie'] ?? null;
        if ($header === null) {
            return false;
        }
        foreach ($header['values'] as $value) {
            if (str_starts_with($value, $name . '=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * La stessa risposta senza corpo, come richiesto da HEAD.
     */
    public function withoutBody(): self
    {
        return new self($this->status, $this->headers(), '');
    }

    public function send(HeaderWriter $writer = new NativeHeaderWriter()): void
    {
        if (!$writer->headersSent()) {
            // Non serve dire al mondo quale versione di PHP gira sul server.
            $writer->remove('X-Powered-By');
            $writer->status($this->status);
            foreach ($this->headers as $header) {
                foreach ($header['values'] as $index => $value) {
                    $writer->header($header['name'] . ': ' . $value, $index === 0);
                }
            }
        }

        echo $this->body;
    }

    /**
     * @return array<string, non-empty-list<string>>
     */
    private function headersExcept(string $name): array
    {
        $headers = $this->headers;
        unset($headers[strtolower($name)]);

        $result = [];
        foreach ($headers as $header) {
            $result[$header['name']] = $header['values'];
        }

        return $result;
    }

    private static function assertValidHeader(string $name, string $value): void
    {
        if (preg_match(self::TOKEN_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Nome di intestazione non valido: "%s".', $name));
        }
        // Niente a capo nei valori: impedisce di iniettare altre intestazioni.
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException(sprintf('Valore non valido per l\'intestazione %s.', $name));
        }
    }
}
