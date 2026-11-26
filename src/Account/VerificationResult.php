<?php

declare(strict_types=1);

namespace FourStreet\Account;

use LogicException;

/**
 * Com'è andata l'apertura di un link di conferma.
 */
final readonly class VerificationResult
{
    /**
     * @param ReturnPath $returnTo la destinazione scelta alla registrazione, per i link che valgono
     * @param int $retryAfter secondi di attesa, solo per Throttled
     */
    private function __construct(
        public VerificationStatus $status,
        public ReturnPath $returnTo,
        private ?User $user = null,
        public int $retryAfter = 0,
    ) {}

    public static function verified(User $user, ReturnPath $returnTo): self
    {
        return new self(VerificationStatus::Verified, $returnTo, $user);
    }

    public static function passwordRequired(User $user, ReturnPath $returnTo): self
    {
        return new self(VerificationStatus::PasswordRequired, $returnTo, $user);
    }

    public static function wrongPassword(User $user, ReturnPath $returnTo): self
    {
        return new self(VerificationStatus::WrongPassword, $returnTo, $user);
    }

    public static function throttled(User $user, ReturnPath $returnTo, int $retryAfter): self
    {
        return new self(VerificationStatus::Throttled, $returnTo, $user, $retryAfter);
    }

    public static function alreadyVerified(): self
    {
        return new self(VerificationStatus::AlreadyVerified, ReturnPath::default());
    }

    public static function expired(): self
    {
        return new self(VerificationStatus::Expired, ReturnPath::default());
    }

    public static function invalid(): self
    {
        return new self(VerificationStatus::Invalid, ReturnPath::default());
    }

    /** L'account del link: appena confermato (Verified) oppure ancora da confermare con la password. */
    public function user(): User
    {
        return $this->user ?? throw new LogicException('Nessun utente: il link non vale.');
    }
}
