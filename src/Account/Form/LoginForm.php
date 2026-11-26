<?php

declare(strict_types=1);

namespace FourStreet\Account\Form;

use FourStreet\Account\EmailAddress;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;
use SensitiveParameter;

/**
 * Il modulo di accesso: email e password. Qui si controlla solo che ci siano; se sono giuste lo dice Login.
 */
final readonly class LoginForm
{
    public const string EMAIL = 'email';
    public const string PASSWORD = 'password';

    /** Il messaggio unico per email sconosciuta, password sbagliata ed email non ancora confermata. */
    public const string WRONG_CREDENTIALS = 'Email o password non corretti.';

    private function __construct(
        public string $email,
        public FormErrors $errors,
        #[SensitiveParameter]
        private string $password = '',
    ) {}

    public static function blank(): self
    {
        return new self('', new FormErrors());
    }

    public static function fromRequest(Request $request): self
    {
        $email = $request->bodyParam(self::EMAIL) ?? '';
        $password = $request->bodyParam(self::PASSWORD) ?? '';

        $errors = new FormErrors();
        $emailProblem = FieldRules::emailProblem($email);
        if ($emailProblem !== null) {
            $errors = $errors->with(self::EMAIL, $emailProblem);
        }
        if ($password === '') {
            $errors = $errors->with(self::PASSWORD, 'Scrivi la password.');
        }

        return new self($email, $errors, $password);
    }

    /** Lo stesso modulo, con l'errore unico delle credenziali sbagliate. */
    public function withWrongCredentials(): self
    {
        return new self($this->email, (new FormErrors())->with(self::EMAIL, self::WRONG_CREDENTIALS), $this->password);
    }

    public function isValid(): bool
    {
        return $this->errors->isEmpty();
    }

    public function emailAddress(): EmailAddress
    {
        return EmailAddress::tryFrom($this->email) ?? throw new LogicException('Modulo di accesso non valido.');
    }

    public function password(): string
    {
        return $this->isValid() ? $this->password : throw new LogicException('Modulo di accesso non valido.');
    }
}
