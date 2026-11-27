<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Clock\Clock;
use FourStreet\Security\Password\PasswordHasher;
use FourStreet\Security\RateLimit\RateLimiter;
use SensitiveParameter;

/**
 * L'accesso con email e password. Una sola risposta per email sconosciuta, password sbagliata ed email
 * non ancora confermata, e lo stesso lavoro in tutti i casi: la password si verifica sempre, su un hash
 * finto se l'account non c'è, così né il testo né il tempo di risposta dicono se l'email è registrata.
 * Un account in sospeso non si distingue nemmeno con la password giusta: chiunque può registrare un
 * indirizzo non suo, e la risposta direbbe se quell'indirizzo aveva già un account confermato.
 * I tentativi si contano prima di verificare la password (tanti tentativi mandati insieme non passano il
 * limite): per indirizzo IP, per email dallo stesso indirizzo e per email da tutti gli indirizzi, con un tetto
 * che da un indirizzo solo non si raggiunge (AccountThrottle::hitLogin()). Un accesso riuscito azzera i
 * tentativi di quell'email da quell'indirizzo.
 */
final readonly class Login
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    public function attempt(EmailAddress $email, #[SensitiveParameter] string $password, #[SensitiveParameter] string $ip): LoginResult
    {
        $status = AccountThrottle::hitLogin($this->limiter, $email, $ip);
        if (!$status->allowed) {
            return LoginResult::throttled($status->retryAfter);
        }

        $user = $this->users->findByEmail($email);
        $valid = $this->passwords->verify($password, $user->passwordHash ?? $this->passwords->dummyHash());
        if ($user === null || !$valid || !$user->isVerified()) {
            return LoginResult::wrongCredentials();
        }

        AccountThrottle::resetLogin($this->limiter, $email, $ip);
        // Parametri dell'hash cambiati (Argon2id più costoso, PHP nuovo): si rifà ora che la password è nota.
        if ($this->passwords->needsRehash($user->passwordHash)) {
            $this->users->updatePasswordHash($user->id, $this->passwords->hash($password), $this->clock->now());
        }

        return LoginResult::success($user);
    }
}
