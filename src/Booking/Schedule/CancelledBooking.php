<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\GymEmail;
use FourStreet\Booking\RosterEntry;

/**
 * Una prenotazione disdetta da una modifica dell'orario, con la sua persona e com'è rimasta l'email della palestra dopo
 * questa richiesta: partita, non partita, in attesa (il giro delle email si è fermato prima: il tempo, o il server di
 * posta che non risponde), in invio (l'ha presa nello stesso istante un altro invio). Null per chi non ha l'account:
 * nessuna email, lo avvisa il titolare.
 */
final readonly class CancelledBooking
{
    public function __construct(
        public RosterEntry $entry,
        public ?GymEmail $email,
    ) {}
}
