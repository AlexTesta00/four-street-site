<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\RosterEntry;

/**
 * Una prenotazione disdetta da una modifica dell'orario, con la sua persona e com'è andata l'email
 * (NoAddress per chi non ha l'account: lo avvisa il titolare).
 */
final readonly class CancelledBooking
{
    public function __construct(
        public RosterEntry $entry,
        public EmailDelivery $email,
    ) {}
}
