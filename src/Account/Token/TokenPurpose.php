<?php

declare(strict_types=1);

namespace FourStreet\Account\Token;

/**
 * A cosa serve il link di un'email, e quanto vale.
 */
enum TokenPurpose: string
{
    /** Conferma dell'email dopo la registrazione: 48 ore. */
    case EmailVerification = 'email_verification';

    /** Scelta di una nuova password: un'ora. */
    case PasswordReset = 'password_reset';

    public function lifetimeSeconds(): int
    {
        return match ($this) {
            self::EmailVerification => 48 * 60 * 60,
            self::PasswordReset => 60 * 60,
        };
    }
}
