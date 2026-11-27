<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Security\IpAddress;
use FourStreet\Security\RateLimit\RateLimit;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Security\RateLimit\RateLimitStatus;
use SensitiveParameter;

/**
 * I limiti di tentativi dell'area clienti, a finestre fisse.
 * Indirizzo IP e email non si salvano mai: i contatori usano solo la loro impronta con chiave.
 */
enum AccountThrottle: string
{
    /** Tentativi di accesso dallo stesso indirizzo IP, riusciti compresi: si contano prima di verificare la password. */
    case LoginPerIp = 'accesso-ip';

    /**
     * Tentativi di accesso sulla stessa email dallo stesso indirizzo IP, che l'account esista o no (non rivela
     * nulla): il limite stretto contro chi prova le password di un account. Un accesso riuscito li azzera.
     */
    case LoginPerAccountAndIp = 'accesso-account-ip';

    /**
     * Tentativi di accesso sulla stessa email da tutti gli indirizzi insieme: un tetto più alto del limite
     * per IP, contro chi prova le password di un account da tanti indirizzi. Da un indirizzo solo non si
     * raggiunge, così nessuno blocca da solo l'accesso di un altro.
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

    /**
     * Conta un tentativo di password (l'accesso, o la conferma dell'email da un altro browser) e dice se
     * si può procedere. Si conta prima di verificare la password: tanti tentativi insieme non passano.
     * I limiti si contano uno dopo l'altro e ci si ferma al primo superato: un indirizzo oltre il suo
     * limite non tocca i contatori dell'email, e uno oltre il limite della coppia non tocca il tetto
     * dell'email. Così da un indirizzo solo il tetto cresce al massimo quanto il limite della coppia.
     */
    public static function hitLogin(RateLimiter $limiter, EmailAddress $email, #[SensitiveParameter] string $ip): RateLimitStatus
    {
        $address = IpAddress::rateLimitSubject($ip);
        $status = $limiter->hit(self::LoginPerIp->limit(), $address);
        if ($status->allowed) {
            $status = $limiter->hit(self::LoginPerAccountAndIp->limit(), self::pair($email, $address));
        }
        if ($status->allowed) {
            $status = $limiter->hit(self::LoginPerAccount->limit(), $email->value);
        }

        return $status;
    }

    /**
     * Un accesso riuscito azzera i tentativi di quell'email da quell'indirizzo. Il tetto dell'email resta
     * com'è: chi prova da tanti indirizzi non riparte da zero perché il cliente è entrato.
     */
    public static function resetLogin(RateLimiter $limiter, EmailAddress $email, #[SensitiveParameter] string $ip): void
    {
        $limiter->reset(self::LoginPerAccountAndIp->limit(), self::pair($email, IpAddress::rateLimitSubject($ip)));
    }

    public function limit(): RateLimit
    {
        return match ($this) {
            self::LoginPerIp => new RateLimit($this->value, 20, 15 * 60),
            self::LoginPerAccountAndIp => new RateLimit($this->value, 8, 15 * 60),
            self::LoginPerAccount => new RateLimit($this->value, 40, 15 * 60),
            self::RegistrationPerIp => new RateLimit($this->value, 10, 60 * 60),
            self::EmailRequestPerIp => new RateLimit($this->value, 10, 60 * 60),
            self::EmailsPerAddress => new RateLimit($this->value, 5, 60 * 60),
        };
    }

    /**
     * Il soggetto del limite della coppia: prima l'indirizzo (come rateLimitSubject(), mai con spazi), poi
     * l'email. Due coppie diverse non danno mai lo stesso testo.
     */
    private static function pair(EmailAddress $email, #[SensitiveParameter] string $address): string
    {
        return $address . ' ' . $email->value;
    }
}
