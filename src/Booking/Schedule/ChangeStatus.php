<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

enum ChangeStatus
{
    /** Fatta: l'orario è cambiato, e le prenotazioni dove una fascia è sparita sono disdette. */
    case Applied;

    /** L'orario era già così: nulla da fare. */
    case Unchanged;

    /** Toglie fasce con delle prenotazioni: prima il titolare vede chi è prenotato e conferma. */
    case NeedsConfirmation;

    /** Non si può (un giorno passato, una fascia già iniziata, due fasce che si sovrappongono…). */
    case Refused;

    /** Troppi tentativi in poco tempo: si aspetta. */
    case Throttled;
}
