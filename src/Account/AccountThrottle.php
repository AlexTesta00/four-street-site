<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Security\RateLimit\RateLimit;

/**
 * I limiti di tentativi dell'area clienti, a finestre fisse.
 * Indirizzo IP e email non si salvano mai: i contatori usano solo la loro impronta con chiave.
 */
enum AccountThrottle: string
{
    /** Tentativi di accesso dallo stesso indirizzo IP, riusciti compresi: si contano prima di verificare la password. */
    case LoginPerIp = 'accesso-ip';

    /**
     * Tentativi di accesso sulla stessa email, che l'account esista o no (non rivela nulla).
     * Un accesso riuscito li azzera.
     */
    case LoginPerAccount = 'accesso-account';

    /** Registrazioni dallo stesso indirizzo IP. */
    case RegistrationPerIp = 'registrazione-ip';

    /** Richieste di un nuovo link di conferma o di una nuova password dallo stesso indirizzo IP. */
    case EmailRequestPerIp = 'richiesta-email-ip';

    /**
     * Email dell'account mandate allo stesso indirizzo (conferma, "hai già un account", nuova password):
     * oltre il limite non parte più nulla, in silenzio, così nessuno può riempire la casella di un altro.
     */
    case EmailsPerAddress = 'email-indirizzo';

    public function limit(): RateLimit
    {
        return match ($this) {
            self::LoginPerIp => new RateLimit($this->value, 20, 15 * 60),
            self::LoginPerAccount => new RateLimit($this->value, 8, 15 * 60),
            self::RegistrationPerIp => new RateLimit($this->value, 10, 60 * 60),
            self::EmailRequestPerIp => new RateLimit($this->value, 10, 60 * 60),
            self::EmailsPerAddress => new RateLimit($this->value, 5, 60 * 60),
        };
    }
}
