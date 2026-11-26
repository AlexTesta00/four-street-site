<?php

declare(strict_types=1);

namespace FourStreet\Account\Form;

use FourStreet\Account\PasswordPolicy;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;
use SensitiveParameter;

/**
 * Il modulo della nuova password: una password sola, con le stesse regole della registrazione.
 */
final readonly class NewPasswordForm
{
    public const string PASSWORD = 'password';

    private function __construct(
        public FormErrors $errors,
        #[SensitiveParameter]
        private string $password = '',
    ) {}

    public static function blank(): self
    {
        return new self(new FormErrors());
    }

    public static function fromRequest(Request $request, PasswordPolicy $policy): self
    {
        $password = $request->bodyParam(self::PASSWORD) ?? '';
        $problem = $policy->problem($password);

        return new self($problem === null ? new FormErrors() : (new FormErrors())->with(self::PASSWORD, $problem), $password);
    }

    public function isValid(): bool
    {
        return $this->errors->isEmpty();
    }

    public function password(): string
    {
        return $this->isValid() ? $this->password : throw new LogicException('Modulo della nuova password non valido.');
    }
}
