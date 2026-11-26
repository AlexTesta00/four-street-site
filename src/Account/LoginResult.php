<?php

declare(strict_types=1);

namespace FourStreet\Account;

use LogicException;

/**
 * Com'è andato un tentativo di accesso.
 */
final readonly class LoginResult
{
    /**
     * @param int $retryAfter secondi di attesa, solo per Throttled
     */
    private function __construct(
        public LoginStatus $status,
        private ?User $user = null,
        public int $retryAfter = 0,
    ) {}

    public static function success(User $user): self
    {
        return new self(LoginStatus::Success, $user);
    }

    public static function wrongCredentials(): self
    {
        return new self(LoginStatus::WrongCredentials);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(LoginStatus::Throttled, retryAfter: $retryAfter);
    }

    /** L'utente entrato. */
    public function user(): User
    {
        return $this->user ?? throw new LogicException('Nessun utente: l\'accesso non è riuscito.');
    }
}
