<?php

declare(strict_types=1);

namespace FourStreet\Account;

/**
 * Com'è andato un cambio di ruolo dalla console.
 */
enum RoleChange
{
    /** Il ruolo è cambiato. */
    case Changed;

    /** L'account aveva già quel ruolo: nulla da cambiare. */
    case Unchanged;

    /** Nessun account con quell'email. */
    case UnknownAccount;

    /** L'account non ha ancora confermato l'email: chi l'ha registrato potrebbe non essere il padrone della casella. */
    case Unverified;
}
