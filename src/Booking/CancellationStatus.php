<?php

declare(strict_types=1);

namespace FourStreet\Booking;

enum CancellationStatus
{
    /** Disdetta: il posto è tornato libero. */
    case Cancelled;

    /**
     * La prenotazione era già disdetta (per il cliente: da lui stesso): di solito lo stesso modulo inviato due volte
     * (doppio tocco senza JavaScript, due schede). Nulla cambia e non parte nessuna email.
     */
    case AlreadyCancelled;

    /**
     * Niente da disdire per questo cliente: la prenotazione non c'è, è di un altro, l'ha disdetta la palestra
     * oppure la disdetta è chiusa (la fascia è iniziata, o manca meno di cancel_closes_minutes_before).
     * Per chi chiede è la stessa cosa: una pagina che non esiste.
     */
    case NotFound;

    /** Troppi tentativi in poco tempo: si aspetta. */
    case Throttled;
}
