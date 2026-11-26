<?php

declare(strict_types=1);

namespace FourStreet\Account;

/**
 * Il ruolo di un account. Chi si registra è sempre un cliente; il titolare (admin) si nomina a mano
 * e avrà le sue pagine più avanti.
 */
enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
