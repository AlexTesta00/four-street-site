<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use FourStreet\Account\Housekeeping;
use FourStreet\Account\User;
use FourStreet\Account\UserRepository;
use FourStreet\Clock\Clock;
use FourStreet\Http\Cookie;
use FourStreet\Http\CookiePolicy;
use FourStreet\Http\Request;
use FourStreet\Security\Token;

/**
 * Le sessioni dell'area clienti, sul database (niente $_SESSION).
 * Il browser tiene un token casuale in un cookie HttpOnly e SameSite=Lax (in HTTPS: Secure e __Host-);
 * il database ne tiene solo l'impronta. Ogni accesso crea un token nuovo, l'uscita lo cancella,
 * e dopo un periodo senza visite (30 giorni di solito) la sessione scade.
 */
final readonly class SessionManager
{
    public const string COOKIE = 'session';

    /** Una visita allunga la sessione al più una volta ogni 15 minuti: nessuna scrittura a ogni pagina. */
    private const int TOUCH_INTERVAL_SECONDS = 15 * 60;

    /**
     * @param positive-int $idleTimeoutSeconds secondi senza visite dopo cui la sessione scade
     */
    public function __construct(
        private SessionRepository $sessions,
        private UserRepository $users,
        private CookiePolicy $cookies,
        private Housekeeping $housekeeping,
        private Clock $clock,
        private int $idleTimeoutSeconds,
    ) {}

    /**
     * Riconosce il visitatore dal cookie. Senza cookie non tocca il database.
     */
    public function resolve(Request $request): SessionState
    {
        $value = $this->cookies->read($request, self::COOKIE);
        if ($value === null) {
            return SessionState::guest();
        }

        $now = $this->clock->now();
        $token = Token::fromString($value);
        $session = $token === null ? null : $this->sessions->find($token->hash());
        $user = $session === null || $session->isExpiredAt($now) ? null : $this->users->findById($session->userId);
        if ($token === null || $session === null || $user === null || !$user->isVerified()) {
            if ($session !== null) {
                $this->sessions->delete($session->tokenHash);
            }

            // Un cookie scaduto, revocato o falso: il browser lo butta.
            return SessionState::guest($this->cookies->expired(self::COOKIE));
        }

        if ($now->getTimestamp() - $session->lastSeenAt->getTimestamp() < self::TOUCH_INTERVAL_SECONDS) {
            return SessionState::member($user);
        }
        $this->sessions->touch($session->tokenHash, $now, $now->modify(sprintf('+%d seconds', $this->idleTimeoutSeconds)));

        return SessionState::member($user, $this->cookie($token));
    }

    /**
     * Apre una sessione nuova per l'utente, con un token nuovo, e chiude quella che questo browser
     * aveva prima. Il cookie restituito va nella risposta.
     */
    public function start(User $user, Request $request): Cookie
    {
        $this->forget($request);
        // Pulizia opportunistica: gli accessi sono pochi, e ognuno porta via ciò che è scaduto.
        $this->housekeeping->run();

        $token = Token::generate();
        $now = $this->clock->now();
        $this->sessions->create(new Session(
            $token->hash(),
            $user->id,
            $now,
            $now,
            $now->modify(sprintf('+%d seconds', $this->idleTimeoutSeconds)),
        ));

        return $this->cookie($token);
    }

    /**
     * Chiude la sessione di questo browser: il cookie restituito (scaduto) va nella risposta.
     */
    public function end(Request $request): Cookie
    {
        $this->forget($request);

        return $this->cookies->expired(self::COOKIE);
    }

    /** Chiude tutte le sessioni dell'utente, su ogni dispositivo (per esempio dopo il cambio della password). */
    public function endAll(User $user): void
    {
        $this->sessions->deleteForUser($user->id);
    }

    private function forget(Request $request): void
    {
        $token = Token::fromString($this->cookies->read($request, self::COOKIE));
        if ($token !== null) {
            $this->sessions->delete($token->hash());
        }
    }

    private function cookie(Token $token): Cookie
    {
        return $this->cookies->cookie(self::COOKIE, $token->value, $this->idleTimeoutSeconds);
    }
}
