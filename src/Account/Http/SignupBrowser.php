<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Http\Cookie;
use FourStreet\Http\CookiePolicy;
use FourStreet\Http\Request;
use FourStreet\Security\Token;

/**
 * Il browser da cui è partita la registrazione. Riceve un cookie con un token casuale; l'account in attesa
 * di conferma ne tiene l'impronta. Il link di conferma basta da solo solo in quel browser, cioè per chi ha
 * scelto la password: aperto altrove chiede anche la password (EmailVerification). Così il link di una
 * registrazione fatta da un altro non attiva l'account di chi lo apre, non fa entrare nessuno con una
 * password che non conosce, e un programma che apre i link per controllarli non cambia nulla.
 * Il cookie parte uguale con ogni registrazione, che l'email abbia già un account o no.
 */
final readonly class SignupBrowser
{
    public const string COOKIE = 'signup';

    /** Una settimana: più dei link di conferma (48 ore), anche quando se ne chiede uno nuovo. */
    private const int LIFETIME_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(
        private CookiePolicy $cookies,
    ) {}

    /** Il cookie da mandare con la risposta alla registrazione. */
    public function cookie(Token $token): Cookie
    {
        return $this->cookies->cookie(self::COOKIE, $token->value, self::LIFETIME_SECONDS);
    }

    /** L'impronta del token che questo browser ha ricevuto alla registrazione, oppure null. */
    public function hash(Request $request): ?string
    {
        return Token::fromString($this->cookies->read($request, self::COOKIE))?->hash();
    }

    /** Il cookie da cancellare, dopo la conferma. */
    public function forget(): Cookie
    {
        return $this->cookies->expired(self::COOKIE);
    }
}
