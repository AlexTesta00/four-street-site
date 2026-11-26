<?php

declare(strict_types=1);

namespace FourStreet\Http;

use InvalidArgumentException;
use Stringable;

/**
 * Origine pubblica del sito: schema, host e porta, senza percorso ("https://fourstreet.studio").
 * Da qui nascono i link assoluti (quelli delle email) e il controllo dell'intestazione Origin:
 * mai dall'intestazione Host della richiesta, che il client può scrivere come vuole.
 * In HTTP solo in locale (localhost, 127.0.0.1, ::1): fuori dal proprio computer il sito è sempre in HTTPS.
 */
final readonly class Origin implements Stringable
{
    private const array LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
    ) {}

    /**
     * @param string $url "https://fourstreet.studio" (una barra finale è ammessa, un percorso no)
     */
    public static function fromUrl(string $url): self
    {
        $parts = preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1 ? false : parse_url($url);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('deve essere un indirizzo completo, come "https://fourstreet.studio"');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new InvalidArgumentException('deve essere solo l\'origine, senza percorso: "https://fourstreet.studio"');
        }

        $origin = new self($scheme, $host, $parts['port'] ?? null);
        if (!$origin->isSecure() && !$origin->isLocal()) {
            throw new InvalidArgumentException('deve iniziare con https:// (http:// vale solo per localhost e 127.0.0.1)');
        }

        return $origin;
    }

    /** I cookie possono viaggiare solo in HTTPS (Secure, prefisso __Host-). */
    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }

    /** Il sito gira sul computer di chi sviluppa. */
    public function isLocal(): bool
    {
        return in_array($this->host, self::LOCAL_HOSTS, true);
    }

    /**
     * Indirizzo assoluto di un percorso del sito.
     *
     * @param string $path percorso con la barra iniziale, anche con la query string: "/account/verifica/?token=…"
     */
    public function url(string $path): string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new InvalidArgumentException(sprintf('Il percorso deve iniziare con una sola barra: "%s".', $path));
        }

        return $this . $path;
    }

    /** "https://fourstreet.studio", con la porta solo se non è quella predefinita dello schema. */
    public function __toString(): string
    {
        $default = $this->isSecure() ? 443 : 80;

        return $this->scheme . '://' . $this->host . ($this->port === null || $this->port === $default ? '' : ':' . $this->port);
    }
}
