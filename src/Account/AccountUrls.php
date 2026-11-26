<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Security\Token;

/**
 * Gli indirizzi dell'area clienti, in un posto solo: rotte, link delle email e redirect li prendono da qui.
 */
final class AccountUrls
{
    public const string HOME = '/account/';
    public const string REGISTER = '/account/registrati/';
    public const string REGISTER_SENT = '/account/registrati/controlla-la-posta/';
    public const string VERIFY = '/account/verifica/';
    public const string VERIFY_RESEND = '/account/verifica/nuovo-link/';
    public const string LOGIN = '/account/accedi/';
    public const string LOGOUT = '/account/esci/';
    public const string FORGOT_PASSWORD = '/account/password-dimenticata/';
    public const string FORGOT_PASSWORD_SENT = '/account/password-dimenticata/controlla-la-posta/';
    public const string RESET_PASSWORD = '/account/nuova-password/';

    /** Le pagine dell'accesso stesso: dopo l'accesso non si torna mai lì (comprese quelle sotto di loro). */
    public const array AUTH_PAGES = [
        self::REGISTER,
        self::VERIFY,
        self::LOGIN,
        self::LOGOUT,
        self::FORGOT_PASSWORD,
        self::RESET_PASSWORD,
    ];

    /** Il parametro del token nei link delle email. */
    public const string TOKEN_PARAMETER = 'token';

    /**
     * Un percorso dell'area clienti con la destinazione di ritorno (se non è quella predefinita) e un avviso.
     */
    public static function to(string $path, ?ReturnPath $returnTo = null, ?Notice $notice = null): string
    {
        $query = [];
        if ($returnTo !== null && !$returnTo->isDefault()) {
            $query[] = ReturnPath::PARAMETER . '=' . self::encode($returnTo->value);
        }
        if ($notice !== null) {
            $query[] = Notice::PARAMETER . '=' . $notice->value;
        }

        return $query === [] ? $path : $path . '?' . implode('&', $query);
    }

    /** Il link di conferma dell'email. */
    public static function verify(Token $token): string
    {
        return self::VERIFY . '?' . self::TOKEN_PARAMETER . '=' . $token->value;
    }

    /** Il link per scegliere una nuova password. */
    public static function resetPassword(Token $token): string
    {
        return self::RESET_PASSWORD . '?' . self::TOKEN_PARAMETER . '=' . $token->value;
    }

    /** Valore di un parametro: codificato, ma con le barre leggibili ("ritorno=/account/"). */
    private static function encode(string $value): string
    {
        return str_replace('%2F', '/', rawurlencode($value));
    }
}
