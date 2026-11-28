<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

/**
 * Com'è andato "Invia le email rimaste" (GymCancellations::resend()).
 */
enum ResendStatus
{
    /** Un giro di invii è partito: quante email sono partite, e se si è fermato prima della fine, lo dice ResendOutcome. */
    case Sent;

    /** Nessuna email da mandare: nessuna in attesa né non partita (magari le ha appena mandate un altro invio). */
    case NothingToSend;

    /** Troppi invii in poco tempo: non si è mandato nulla. */
    case Throttled;
}
