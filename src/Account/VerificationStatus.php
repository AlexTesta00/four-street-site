<?php

declare(strict_types=1);

namespace FourStreet\Account;

enum VerificationStatus
{
    /** Email confermata adesso: si entra. */
    case Verified;

    /**
     * Link valido, aperto fuori dal browser della registrazione: l'email si conferma solo con la password
     * scelta registrandosi. Il link non è ancora usato.
     */
    case PasswordRequired;

    /** La password scritta per confermare non è quella dell'account. */
    case WrongPassword;

    /** Troppi tentativi di password: si aspetta. */
    case Throttled;

    /** Il link era già stato usato e l'email è confermata: si accede con email e password. */
    case AlreadyVerified;

    /** Il link è scaduto: se ne chiede un altro. */
    case Expired;

    /** Il link non esiste, è incompleto o non vale più: se ne chiede un altro. */
    case Invalid;
}
