<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Lo stato di un giorno nella scelta dei giorni.
 */
enum DayState
{
    /** Almeno una fascia si può prenotare. */
    case Open;

    /** Ci sono fasce che si prenotano ancora, ma sono tutte piene. */
    case Full;

    /** Nessuna fascia da prenotare: giorno di chiusura, oppure le fasce sono già iniziate o passate. */
    case Closed;
}
