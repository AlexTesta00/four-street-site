<?php

declare(strict_types=1);

namespace FourStreet\Security\Csrf;

use FourStreet\Http\Controller;
use FourStreet\Http\CookiePolicy;
use FourStreet\Http\Middleware\Middleware;
use FourStreet\Http\Origin;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Token;

/**
 * Protezione dei moduli dalle richieste false partite da altri siti (CSRF), anche prima dell'accesso.
 *
 * Prima barriera, double-submit cookie: il browser riceve un token casuale in un cookie (con il prefisso
 * __Host- in HTTPS, così nessun sottodominio può impostarlo) e lo stesso token sta nel campo nascosto
 * del modulo. Un altro sito non può leggere il cookie, quindi non può scrivere il campo giusto.
 * Il confronto è a tempo costante.
 * Seconda barriera: Sec-Fetch-Site (o, nei browser che non lo mandano, Origin) deve dire che il modulo
 * è partito da questo sito.
 */
final readonly class CsrfProtection implements Middleware
{
    /** Il campo nascosto dei moduli. */
    public const string FIELD = 'csrf';

    private const string COOKIE = 'csrf';

    /**
     * @param Controller $rejected la pagina per un modulo respinto (403)
     */
    public function __construct(
        private CookiePolicy $cookies,
        private Origin $origin,
        private Controller $rejected,
    ) {}

    public function process(Request $request, Controller $next): Response
    {
        $existing = Token::fromString($this->cookies->read($request, self::COOKIE));
        $token = $existing ?? Token::generate();
        $request = $request->withContext(new CsrfToken($token->value));

        $response = $request->isSafe() || $this->accepts($request, $existing)
            ? $next($request)
            : ($this->rejected)($request);

        // Il cookie dura quanto la sessione del browser; si imposta solo quando manca.
        return $existing === null ? $response->withCookie($this->cookies->cookie(self::COOKIE, $token->value)) : $response;
    }

    private function accepts(Request $request, ?Token $cookie): bool
    {
        $field = $request->bodyParam(self::FIELD);

        return $cookie !== null
            && $field !== null
            && hash_equals($cookie->value, $field)
            && $this->sentFromThisSite($request);
    }

    private function sentFromThisSite(Request $request): bool
    {
        $site = $request->header('Sec-Fetch-Site');
        if ($site !== null) {
            // "none": l'ha chiesto la persona (indirizzo scritto a mano, segnalibro), mai un altro sito.
            return in_array(strtolower($site), ['same-origin', 'none'], true);
        }
        $origin = $request->header('Origin');

        return $origin === null || $origin === (string) $this->origin;
    }
}
