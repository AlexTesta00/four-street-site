<?php

declare(strict_types=1);

namespace FourStreet\Http;

use SensitiveParameter;

/**
 * Come il sito imposta i suoi cookie: sempre HttpOnly, SameSite=Lax e Path=/. Se il sito è in HTTPS
 * anche Secure e con il prefisso __Host-; su http://localhost in sviluppo senza, altrimenti il browser
 * li scarterebbe. La scelta segue l'origine pubblica del sito, mai la richiesta.
 */
final readonly class CookiePolicy
{
    public function __construct(
        public bool $secure,
    ) {}

    public static function for(Origin $origin): self
    {
        return new self($origin->isSecure());
    }

    /** Il nome con cui il cookie viaggia: "session" diventa "__Host-session" in HTTPS. */
    public function name(string $name): string
    {
        return $this->secure ? '__Host-' . $name : $name;
    }

    /**
     * @param string $name nome senza prefisso, per esempio "session"
     * @param int|null $maxAge secondi di vita: null finché il browser resta aperto
     */
    public function cookie(string $name, #[SensitiveParameter] string $value, ?int $maxAge = null): Cookie
    {
        return new Cookie($this->name($name), $value, $maxAge, $this->secure);
    }

    /** Il cookie da cancellare. */
    public function expired(string $name): Cookie
    {
        return $this->cookie($name, '')->expired();
    }

    /** Il valore del cookie nella richiesta, oppure null. */
    public function read(Request $request, string $name): ?string
    {
        return $request->cookie($this->name($name));
    }
}
