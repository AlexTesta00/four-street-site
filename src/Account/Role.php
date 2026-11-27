<?php

declare(strict_types=1);

namespace FourStreet\Account;

/**
 * Il ruolo di un account. Chi si registra è sempre un cliente; il titolare (admin) si nomina dalla console
 * (bin/console admin:grant) e ha il pannello delle prenotazioni, /gestione/.
 */
enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
