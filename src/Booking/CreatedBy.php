<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Chi ha inserito una prenotazione: il cliente dal suo account, oppure la palestra (il titolare) dal pannello.
 * Una persona senza account la inserisce sempre la palestra.
 */
enum CreatedBy: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
