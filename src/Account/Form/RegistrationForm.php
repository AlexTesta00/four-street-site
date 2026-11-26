<?php

declare(strict_types=1);

namespace FourStreet\Account\Form;

use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\PasswordPolicy;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;
use SensitiveParameter;

/**
 * Il modulo di registrazione: nome e cognome, email, password, informativa sulla privacy.
 * I valori scritti tornano nel modulo se c'è un errore, la password mai.
 */
final readonly class RegistrationForm
{
    public const string NAME = 'nome';
    public const string EMAIL = 'email';
    public const string PASSWORD = 'password';
    public const string PRIVACY = 'privacy';

    private function __construct(
        public string $name,
        public string $email,
        public bool $privacy,
        public FormErrors $errors,
        #[SensitiveParameter]
        private string $password = '',
    ) {}

    public static function blank(): self
    {
        return new self('', '', false, new FormErrors());
    }

    public static function fromRequest(Request $request, PasswordPolicy $policy): self
    {
        $name = $request->bodyParam(self::NAME) ?? '';
        $email = $request->bodyParam(self::EMAIL) ?? '';
        $password = $request->bodyParam(self::PASSWORD) ?? '';
        $privacy = $request->bodyParam(self::PRIVACY) !== null;

        $errors = new FormErrors();
        foreach ([
            self::NAME => FieldRules::nameProblem($name),
            self::EMAIL => FieldRules::emailProblem($email),
            self::PASSWORD => $policy->problem($password),
            self::PRIVACY => $privacy ? null : 'Per creare l\'account conferma di aver letto l\'informativa sulla privacy.',
        ] as $field => $problem) {
            if ($problem !== null) {
                $errors = $errors->with($field, $problem);
            }
        }

        return new self($name, $email, $privacy, $errors, $password);
    }

    public function isValid(): bool
    {
        return $this->errors->isEmpty();
    }

    public function fullName(): FullName
    {
        return FullName::tryFrom($this->name) ?? throw new LogicException('Modulo di registrazione non valido.');
    }

    public function emailAddress(): EmailAddress
    {
        return EmailAddress::tryFrom($this->email) ?? throw new LogicException('Modulo di registrazione non valido.');
    }

    public function password(): string
    {
        return $this->isValid() ? $this->password : throw new LogicException('Modulo di registrazione non valido.');
    }
}
