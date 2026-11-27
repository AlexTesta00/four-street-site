<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

/**
 * Da dove viene l'orario di un giorno.
 */
enum DayKind
{
    /** Segue la settimana tipo (anche quando quel giorno della settimana non ha fasce, come la domenica). */
    case Week;

    /** Ha un orario suo, scelto dal titolare al posto della settimana tipo. */
    case Changed;

    /** È chiuso: una chiusura del titolare, oppure uno dei closed_dates del file. Nessuna fascia. */
    case Closed;
}
