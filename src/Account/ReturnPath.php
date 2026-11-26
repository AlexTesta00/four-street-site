<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Http\Request;

/**
 * La pagina del sito a cui tornare dopo l'accesso o la registrazione ("stavo prenotando questa fascia").
 * Arriva dal parametro "ritorno" e resta valida solo se è un percorso interno, scritto in modo pulito:
 * una sola barra iniziale, solo caratteri da URL, niente segmenti "..", mai una pagina dell'accesso.
 * Tutto il resto vale la pagina dell'account: nessun redirect verso altri siti (open redirect).
 */
final readonly class ReturnPath
{
    public const string PARAMETER = 'ritorno';

    private const int MAX_LENGTH = 512;

    /**
     * Una sola barra iniziale (né "//" né "/\": il browser li leggerebbe come un altro sito),
     * poi solo i caratteri di un percorso e di una query string (RFC 3986): niente spazi, "\", "#", virgolette.
     */
    private const string PATTERN = '#\A/(?![/\\\\])[A-Za-z0-9\-._~!$&\'()*+,;=:@/?%]*\z#';

    private function __construct(
        public string $value,
    ) {}

    /** La pagina dell'account. */
    public static function default(): self
    {
        return new self(AccountUrls::HOME);
    }

    /** La destinazione chiesta (parametro dell'indirizzo o campo del modulo); se non è sicura, quella predefinita. */
    public static function fromInput(?string $value): self
    {
        return $value !== null && self::isSafe($value) ? new self($value) : self::default();
    }

    /** La destinazione indicata dal parametro "ritorno" dell'indirizzo della richiesta. */
    public static function fromQuery(Request $request): self
    {
        return self::fromInput($request->queryParam(self::PARAMETER));
    }

    /** La destinazione indicata dal campo nascosto "ritorno" del modulo inviato. */
    public static function fromBody(Request $request): self
    {
        return self::fromInput($request->bodyParam(self::PARAMETER));
    }

    /** La pagina stessa della richiesta, per tornarci dopo l'accesso. */
    public static function fromRequest(Request $request): self
    {
        $path = implode('/', array_map(rawurlencode(...), explode('/', $request->path)));

        return self::fromInput($request->pathWithQuery($path));
    }

    public function isDefault(): bool
    {
        return $this->value === AccountUrls::HOME;
    }

    private static function isSafe(string $value): bool
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            return false;
        }
        $path = explode('?', $value, 2)[0];
        if (preg_match('#(?:\A|/)\.{1,2}(?:/|\z)#', rawurldecode($path)) === 1) {
            return false;
        }
        foreach (AccountUrls::AUTH_PAGES as $page) {
            if (str_starts_with($path, $page)) {
                return false;
            }
        }

        return true;
    }
}
