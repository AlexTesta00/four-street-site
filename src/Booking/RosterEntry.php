<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\EmailAddress;

/**
 * Una prenotazione con la sua persona, per la palestra: il nome (quello dell'account, oppure quello scritto
 * dal titolare per chi non ha l'account) e, solo per un cliente registrato, l'email. Sono dati personali:
 * li vede soltanto il pannello del titolare.
 */
final readonly class RosterEntry
{
    /**
     * @param EmailAddress|null $email l'email dell'account; null per una persona senza account
     */
    public function __construct(
        public Booking $booking,
        public string $name,
        public ?EmailAddress $email,
    ) {}
}
