<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Account\Token\AccountTokens;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Clock\Clock;
use FourStreet\Security\IpAddress;
use FourStreet\Security\Password\PasswordHasher;
use FourStreet\Security\RateLimit\RateLimiter;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * La registrazione. La risposta è sempre la stessa, che l'email abbia già un account o no
 * (nessuno deve poter scoprire chi è cliente); cambia solo l'email che arriva nella casella:
 * - email nuova: account non confermato e link di conferma;
 * - account confermato: "hai già un account", con i link per accedere o scegliere una nuova password;
 * - account mai confermato: la registrazione nuova prende il posto di quella in sospeso (nessuno ha
 *   ancora dimostrato di possedere l'email) e parte un link nuovo, che annulla i precedenti.
 * Il link basta da solo solo nel browser che ha inviato la registrazione ($browserHash, vedi
 * SignupBrowser): chi lo apre altrove conferma l'email scrivendo la password scelta qui.
 */
final readonly class Registration
{
    public function __construct(
        private UserRepository $users,
        private AccountTokens $tokens,
        private PasswordHasher $passwords,
        private AccountEmails $emails,
        private AccountMailer $mail,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    public function register(
        FullName $name,
        EmailAddress $email,
        #[SensitiveParameter]
        string $password,
        ReturnPath $returnTo,
        string $ip,
        string $browserHash,
    ): Delivery {
        $ipStatus = $this->limiter->hit(AccountThrottle::RegistrationPerIp->limit(), IpAddress::rateLimitSubject($ip));
        if (!$ipStatus->allowed) {
            return Delivery::throttled($ipStatus->retryAfter);
        }

        // L'hash si calcola sempre, anche quando l'account esiste: il tempo di risposta non deve rivelarlo.
        $registration = new NewUser($name, $email, $this->passwords->hash($password), $this->clock->now(), $browserHash);
        if (!$this->mail->reserve($email)) {
            return Delivery::sent();
        }

        $user = $this->users->findByEmail($email) ?? $this->create($registration);
        if ($user->isVerified()) {
            return $this->mail->send($this->emails->alreadyRegistered($user, $returnTo));
        }

        // Per un account appena creato non cambia nulla; per uno in sospeso valgono i dati nuovi.
        $this->users->replaceUnverified($user->id, $registration);
        $user = $this->users->findById($user->id) ?? throw new UnexpectedValueException('Account sparito durante la registrazione.');
        $token = $this->tokens->issue($user, TokenPurpose::EmailVerification, $returnTo);

        return $this->mail->send($this->emails->verification($user, $token));
    }

    private function create(NewUser $registration): User
    {
        try {
            return $this->users->create($registration);
        } catch (DuplicateEmail) {
            // Un'altra registrazione con la stessa email è arrivata un istante prima: si prosegue da quella.
            return $this->users->findByEmail($registration->email)
                ?? throw new UnexpectedValueException('Account sparito durante la registrazione.');
        }
    }
}
