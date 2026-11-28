<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Com'è andata l'email di una prenotazione o di una disdetta, mandata subito: la prenotazione vale
 * comunque, e se l'email non è partita la pagina lo dice.
 */
enum EmailDelivery
{
    case Sent;
    case Failed;

    /** Nessuna email da mandare: la persona non ha un account, quindi nemmeno un indirizzo (la avvisa la palestra). */
    case NoAddress;

    /**
     * Non partita da questa richiesta, e ancora dovuta: l'email della palestra (GymEmail) l'ha presa un altro invio
     * nello stesso istante. Com'è finita lo dice la pagina Disdette.
     */
    case Pending;
}
