<?php

declare(strict_types=1);

namespace FourStreet\Account;

/**
 * Com'è andata una richiesta che manda un'email (registrazione, nuovo link, nuova password).
 * "Sent" vale anche quando l'email, per non rivelare nulla, non parte affatto (indirizzo sconosciuto,
 * troppe email allo stesso indirizzo) o partirà dopo la risposta: la pagina risponde sempre uguale.
 * "Failed" c'è solo per gli invii immediati (la registrazione), dove la persona deve poter riprovare.
 */
final readonly class Delivery
{
    /**
     * @param int $retryAfter secondi di attesa, solo per Throttled
     */
    private function __construct(
        public DeliveryStatus $status,
        public int $retryAfter = 0,
    ) {}

    public static function sent(): self
    {
        return new self(DeliveryStatus::Sent);
    }

    /** L'email doveva partire e non è partita: la pagina propone di riprovare. */
    public static function failed(): self
    {
        return new self(DeliveryStatus::Failed);
    }

    /** Troppe richieste dallo stesso indirizzo IP. */
    public static function throttled(int $retryAfter): self
    {
        return new self(DeliveryStatus::Throttled, $retryAfter);
    }
}
