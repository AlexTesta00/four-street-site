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
}
