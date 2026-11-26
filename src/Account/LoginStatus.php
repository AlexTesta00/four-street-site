<?php

declare(strict_types=1);

namespace FourStreet\Account;

enum LoginStatus
{
    /** Email e password giuste, email confermata: si entra. */
    case Success;

    /**
     * Nessun account con quell'email, password sbagliata o email non ancora confermata:
     * la stessa risposta per tutti.
     */
    case WrongCredentials;

    /** Troppi tentativi: si aspetta. */
    case Throttled;
}
