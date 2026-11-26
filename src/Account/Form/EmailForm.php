<?php

declare(strict_types=1);

namespace FourStreet\Account\Form;

use FourStreet\Account\EmailAddress;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;

/**
 * Un modulo con la sola email: per chiedere un nuovo link di conferma o una nuova password.
 */
final readonly class EmailForm
{
    public const string EMAIL = 'email';

    private function __construct(
        public string $email,
        public FormErrors $errors,
    ) {}

    public static function blank(string $email = ''): self
    {
        return new self($email, new FormErrors());
    }

    public static function fromRequest(Request $request): self
    {
        $email = $request->bodyParam(self::EMAIL) ?? '';
        $problem = FieldRules::emailProblem($email);

        return new self($email, $problem === null ? new FormErrors() : (new FormErrors())->with(self::EMAIL, $problem));
    }

    public function isValid(): bool
    {
        return $this->errors->isEmpty();
    }

    public function emailAddress(): EmailAddress
    {
        return EmailAddress::tryFrom($this->email) ?? throw new LogicException('Modulo non valido.');
    }
}
