<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Chi ha disdetto una prenotazione: il cliente dal suo account, oppure la palestra (il titolare).
 */
enum CancelledBy: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
