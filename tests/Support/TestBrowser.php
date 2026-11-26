<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Application;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use PHPUnit\Framework\Assert;

/**
 * Un browser finto davanti all'applicazione vera: tiene i cookie fra una richiesta e l'altra
 * (Set-Cookie, Max-Age=0 per cancellare) e manda i moduli come farebbe dalla pagina del sito,
 * con il token CSRF del suo cookie e Sec-Fetch-Site: same-origin.
 */
final class TestBrowser
{
    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(
        private readonly Application $application,
        private readonly string $remoteAddress = '203.0.113.7',
    ) {}

    /**
     * @param array<string, string> $headers
     *
     * @phpstan-impure
     */
    public function get(string $uri, array $headers = []): Response
    {
        return $this->send(Request::create('GET', $uri, $headers, [], $this->cookies, $this->remoteAddress));
    }

    /**
     * @param array<string, string> $headers
     *
     * @phpstan-impure
     */
    public function head(string $uri, array $headers = []): Response
    {
        return $this->send(Request::create('HEAD', $uri, $headers, [], $this->cookies, $this->remoteAddress));
    }

    /**
     * Invia un modulo. Senza un campo "csrf" usa il token del cookie, come il campo nascosto della pagina.
     *
     * @param array<string, string> $fields
     * @param array<string, string> $headers sostituiscono quelli predefiniti (Sec-Fetch-Site: same-origin)
     *
     * @phpstan-impure
     */
    public function post(string $uri, array $fields = [], array $headers = ['Sec-Fetch-Site' => 'same-origin']): Response
    {
        $csrf = $this->csrfToken();
        if ($csrf !== null && !array_key_exists('csrf', $fields)) {
            $fields['csrf'] = $csrf;
        }

        return $this->send(Request::create('POST', $uri, $headers, $fields, $this->cookies, $this->remoteAddress));
    }

    /** Segue un redirect, con GET come fa il browser dopo un 303. */
    public function follow(Response $redirect): Response
    {
        Assert::assertContains($redirect->status, [301, 302, 303, 307, 308], 'La risposta non è un redirect.');
        $location = $redirect->header('Location');
        Assert::assertNotNull($location);

        return $this->get($location);
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** Il token CSRF del cookie, con o senza il prefisso __Host-. */
    public function csrfToken(): ?string
    {
        return $this->cookies['__Host-csrf'] ?? $this->cookies['csrf'] ?? null;
    }

    public function setCookie(string $name, string $value): void
    {
        $this->cookies[$name] = $value;
    }

    public function forgetCookie(string $name): void
    {
        unset($this->cookies[$name]);
    }

    /**
     * I Set-Cookie della risposta, così come sono.
     *
     * @return list<string>
     */
    public static function setCookieHeaders(Response $response): array
    {
        foreach ($response->headers() as $name => $values) {
            if (strtolower($name) === 'set-cookie') {
                return $values;
            }
        }

        return [];
    }

    /** Una richiesta come in index.php: la risposta, poi il lavoro rimandato (le email in coda). */
    private function send(Request $request): Response
    {
        $response = $this->application->handle($request);
        $this->application->finish();
        foreach (self::setCookieHeaders($response) as $line) {
            $pair = strstr($line, ';', true);
            $pair = $pair === false ? $line : $pair;
            $separator = strpos($pair, '=');
            $name = $separator === false ? $pair : substr($pair, 0, $separator);
            $value = $separator === false ? '' : substr($pair, $separator + 1);
            if (preg_match('/;\s*Max-Age=0(?:;|\z)/i', $line) === 1) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }

        return $response;
    }
}
