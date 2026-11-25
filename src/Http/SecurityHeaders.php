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

    public static function forSite(): self
    {
        return new self([
            'Content-Security-Policy' => self::contentSecurityPolicy(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ]);
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
     * Solo ciò che le pagine usano davvero: script e stili nostri, Google Fonts, la mappa di Google.
     */
    private static function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            // Fogli di stile: il nostro e quello di Google Fonts, niente <style> in pagina.
            "style-src-elem 'self' https://fonts.googleapis.com",
            // Gli attributi style del countdown (--sheen-delay, il ritardo del riflesso sulle cifre).
            "style-src-attr 'unsafe-inline'",
            // Per i browser senza CSP 3, che non conoscono le due direttive qui sopra.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self'",
            // La mappa incorporata nello stato "Ci siamo".
            'frame-src https://www.google.com',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
