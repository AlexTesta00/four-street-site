<?php

declare(strict_types=1);

namespace FourStreet\Http;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Un cookie da impostare con Set-Cookie. Nome e valore sono controllati: niente a capo, niente separatori.
 * Un nome con il prefisso __Host- obbliga il browser a tenerlo solo per questo host, in HTTPS, su tutto il sito.
 */
final readonly class Cookie
{
    private const string NAME_PATTERN = "/\\A[!#$%&'*+.^_`|~0-9A-Za-z-]+\\z/";

    /** Caratteri ammessi nel valore (cookie-octet, RFC 6265): niente spazi, virgolette, virgole, punti e virgola, barre rovesciate. */
    private const string VALUE_PATTERN = '/\A[\x21\x23-\x2B\x2D-\x3A\x3C-\x5B\x5D-\x7E]*\z/';

    private const string HOST_PREFIX = '__Host-';

    /**
     * @param int|null $maxAge secondi di vita: null finché il browser resta aperto, 0 per cancellarlo
     */
    public function __construct(
        public string $name,
        #[SensitiveParameter]
        public string $value,
        public ?int $maxAge = null,
        public bool $secure = true,
        public bool $httpOnly = true,
        public SameSite $sameSite = SameSite::Lax,
        public string $path = '/',
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Nome di cookie non valido: "%s".', $name));
        }
        if (preg_match(self::VALUE_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Valore non valido per il cookie %s.', $name));
        }
        if ($maxAge !== null && $maxAge < 0) {
            throw new InvalidArgumentException(sprintf('Durata non valida per il cookie %s: %d.', $name, $maxAge));
        }
        if (!str_starts_with($path, '/') || preg_match('/[;\s\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException(sprintf('Percorso non valido per il cookie %s: "%s".', $name, $path));
        }
        if (str_starts_with($name, self::HOST_PREFIX) && (!$secure || $path !== '/')) {
            throw new InvalidArgumentException(sprintf('Il cookie %s vuole Secure e Path=/.', $name));
        }
    }

    /** Lo stesso cookie da cancellare: valore vuoto e Max-Age=0. */
    public function expired(): self
    {
        return new self($this->name, '', 0, $this->secure, $this->httpOnly, $this->sameSite, $this->path);
    }

    /** Il valore dell'intestazione Set-Cookie. */
    public function headerValue(): string
    {
        $parts = [$this->name . '=' . $this->value];
        if ($this->maxAge !== null) {
            $parts[] = 'Max-Age=' . $this->maxAge;
        }
        $parts[] = 'Path=' . $this->path;
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        $parts[] = 'SameSite=' . $this->sameSite->value;

        return implode('; ', $parts);
    }
}
