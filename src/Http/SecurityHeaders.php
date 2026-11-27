<?php

declare(strict_types=1);

namespace FourStreet\Http;

/**
 * Intestazioni di sicurezza aggiunte a ogni risposta di PHP (i file statici le ricevono da public/.htaccess).
 */
final readonly class SecurityHeaders
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private array $headers,
    ) {}

    /**
     * @param bool $https il sito è in HTTPS. Lo dice l'origine pubblica, come per i cookie Secure (CookiePolicy),
     *                    mai la richiesta: fuori da localhost il .htaccess porta ogni richiesta http su https
     *                    prima che arrivi qui, e su http://localhost l'origine è in http.
     */
    public static function forSite(bool $https = false): self
    {
        $headers = [
            'Content-Security-Policy' => self::contentSecurityPolicy(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($https) {
            // Per un anno il browser non prova più l'HTTP su questo host. Solo questo host: niente
            // includeSubDomains né preload, scelte che coinvolgono altri siti e non si disfano in fretta.
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return new self($headers);
    }

    /**
     * Aggiunge le intestazioni che la risposta non ha già: un controller può sempre decidere diversamente.
     */
    public function applyTo(Response $response): Response
    {
        foreach ($this->headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Solo ciò che le pagine usano davvero: script e stili nostri, Google Fonts. Niente in linea:
     * nessun <style>, nessun attributo style, nessuno script scritto nella pagina.
     */
    private static function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            // Fogli di stile: il nostro e quello di Google Fonts.
            "style-src 'self' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self'",
            // La mappa è un disegno della pagina: nessun sito incorporato.
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
