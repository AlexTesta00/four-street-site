<?php

declare(strict_types=1);

namespace FourStreet\Account\Form;

use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;

/**
 * Il modulo della pagina del link di conferma aperto fuori dal browser della registrazione:
 * la sola password. Se è giusta lo dice EmailVerification; qui ci sono il campo e i suoi messaggi.
 */
final readonly class ConfirmEmailForm
{
    public const string PASSWORD = 'password';

    private function __construct(
        public FormErrors $errors,
    ) {}

    public static function blank(): self
    {
        return new self(new FormErrors());
    }

    /** La password inviata, così com'è (vuota se manca). */
    public static function password(Request $request): string
    {
        return $request->bodyParam(self::PASSWORD) ?? '';
    }

    public static function missingPassword(): self
    {
        return new self((new FormErrors())->with(self::PASSWORD, 'Scrivi la password che hai scelto registrandoti.'));
    }

    public static function wrongPassword(): self
    {
        return new self((new FormErrors())->with(self::PASSWORD, 'Password non corretta.'));
    }
}
