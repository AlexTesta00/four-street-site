<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Http\Request;
use FourStreet\View\Alert;

/**
 * Gli avvisi che una pagina dell'area clienti mostra dopo un redirect ("?avviso=uscito").
 * Il parametro sceglie solo fra questi testi fissi: nessun testo arriva dall'indirizzo.
 */
enum Notice: string
{
    public const string PARAMETER = 'avviso';

    case EmailVerified = 'email-confermata';
    case PasswordChanged = 'password-aggiornata';
    case LoggedOut = 'uscito';
    case MailNotSent = 'email-non-inviata';

    public static function fromRequest(Request $request): ?self
    {
        return self::tryFrom($request->queryParam(self::PARAMETER) ?? '');
    }

    public function alert(): Alert
    {
        return match ($this) {
            self::EmailVerified => Alert::success('Email confermata. Benvenuto in 4Street.'),
            self::PasswordChanged => Alert::success('Password aggiornata. Per sicurezza abbiamo chiuso l\'accesso sugli altri dispositivi.'),
            self::LoggedOut => Alert::info('Sei uscito dal tuo account.'),
            self::MailNotSent => Alert::error('Non siamo riusciti a mandarti l\'email. Riprova tra qualche minuto con il link qui sotto.', 'Email non inviata'),
        };
    }
}
