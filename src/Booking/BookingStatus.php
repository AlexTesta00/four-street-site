<?php

declare(strict_types=1);

namespace FourStreet\Booking;

enum BookingStatus
{
    /** La fascia si può prenotare adesso, e questo è il posto che toccherebbe (il controllo prima di confermare). */
    case Available;

    /** Prenotata: il posto è del cliente. */
    case Booked;

    /** Tutti i posti sono presi. */
    case Full;

    /** Il cliente ha già una prenotazione attiva quel giorno: se ne ha una sola al giorno. */
    case AlreadyBooked;

    /** Il cliente ha già il massimo di prenotazioni future attive (booking.max_upcoming). */
    case TooManyUpcoming;

    /** La fascia c'è ma non si prenota più: è passata, è iniziata o sta per iniziare. */
    case Closed;

    /** Quel giorno a quell'ora non c'è nessuna fascia, o non è ancora fra quelle che si prenotano. */
    case NotFound;

    /** Troppi tentativi in poco tempo: si aspetta. */
    case Throttled;
}
