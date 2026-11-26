<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Account;

use FourStreet\Account\EmailAddress;
use FourStreet\Account\Form\EmailForm;
use FourStreet\Account\Form\FieldRules;
use FourStreet\Account\Form\LoginForm;
use FourStreet\Account\Form\NewPasswordForm;
use FourStreet\Account\Form\RegistrationForm;
use FourStreet\Account\FullName;
use FourStreet\Account\PasswordPolicy;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegistrationForm::class)]
#[CoversClass(LoginForm::class)]
#[CoversClass(EmailForm::class)]
#[CoversClass(NewPasswordForm::class)]
#[CoversClass(FieldRules::class)]
#[UsesClass(EmailAddress::class)]
#[UsesClass(FullName::class)]
#[UsesClass(PasswordPolicy::class)]
#[UsesClass(FormErrors::class)]
#[UsesClass(Request::class)]
final class FormsTest extends TestCase
{
    public function testAValidRegistration(): void
    {
        $form = RegistrationForm::fromRequest(self::post(['nome' => ' Mario   Rossi ', 'email' => 'Mario@Example.com', 'password' => 'una password lunga', 'privacy' => '1']), new PasswordPolicy());

        self::assertTrue($form->isValid());
        self::assertSame('Mario Rossi', $form->fullName()->value);
        self::assertSame('mario@example.com', $form->emailAddress()->value);
        self::assertSame('una password lunga', $form->password());
        self::assertTrue($form->privacy);
        self::assertSame(' Mario   Rossi ', $form->name, 'Il modulo si ricompila con quello che la persona ha scritto.');
    }

    public function testAnInvalidRegistrationKeepsTheFieldOrder(): void
    {
        $form = RegistrationForm::fromRequest(self::post(['nome' => str_repeat('a', 101), 'email' => '', 'password' => '']), new PasswordPolicy());

        self::assertFalse($form->isValid());
        self::assertSame([
            'nome' => 'Nome e cognome possono avere al massimo 100 caratteri.',
            'email' => 'Scrivi la tua email.',
            'password' => 'Scegli una password.',
            'privacy' => 'Per creare l\'account conferma di aver letto l\'informativa sulla privacy.',
        ], $form->errors->all());
        self::assertFalse($form->privacy);
    }

    public function testAnInvalidFormHasNoValuesToGive(): void
    {
        $form = RegistrationForm::fromRequest(self::post(['nome' => 'Mario Rossi', 'email' => 'mario@example.com', 'password' => 'corta', 'privacy' => '1']), new PasswordPolicy());

        $this->expectException(LogicException::class);

        $form->password();
    }

    public function testFieldsSentAsArraysAreIgnored(): void
    {
        $form = RegistrationForm::fromRequest(self::post(['nome' => ['Mario'], 'email' => ['a@b.it'], 'password' => ['x'], 'privacy' => ['1']]), new PasswordPolicy());

        self::assertSame(['nome', 'email', 'password'], array_keys(array_slice($form->errors->all(), 0, 3)));
        self::assertSame('', $form->name);
    }

    public function testTheLoginForm(): void
    {
        $valid = LoginForm::fromRequest(self::post(['email' => 'MARIO@example.com', 'password' => ' con spazi ']));
        $empty = LoginForm::fromRequest(self::post([]));

        self::assertTrue($valid->isValid());
        self::assertSame('mario@example.com', $valid->emailAddress()->value);
        self::assertSame(' con spazi ', $valid->password(), 'La password non si tocca: gli spazi contano.');
        self::assertSame(['email' => 'Scrivi la tua email.', 'password' => 'Scrivi la password.'], $empty->errors->all());
        self::assertSame(['email' => LoginForm::WRONG_CREDENTIALS], $valid->withWrongCredentials()->errors->all());
        self::assertSame('MARIO@example.com', $valid->withWrongCredentials()->email);
        self::assertTrue(LoginForm::blank()->errors->isEmpty());
    }

    public function testTheEmailAndPasswordForms(): void
    {
        self::assertSame('mario@example.com', EmailForm::fromRequest(self::post(['email' => ' mario@example.com']))->emailAddress()->value);
        self::assertSame(['email' => 'Scrivi un indirizzo email valido, come nome@esempio.it.'], EmailForm::fromRequest(self::post(['email' => 'x']))->errors->all());
        self::assertSame('a@b.it', EmailForm::blank('a@b.it')->email);
        self::assertSame('la mia password nuova', NewPasswordForm::fromRequest(self::post(['password' => 'la mia password nuova']), new PasswordPolicy())->password());
        self::assertSame(['password' => 'La password deve avere almeno 8 caratteri.'], NewPasswordForm::fromRequest(self::post(['password' => 'corta']), new PasswordPolicy())->errors->all());
        self::assertTrue(NewPasswordForm::blank()->errors->isEmpty());
    }

    public function testNameRules(): void
    {
        self::assertNull(FieldRules::nameProblem('Mario Rossi'));
        self::assertSame('Scrivi nome e cognome per intero.', FieldRules::nameProblem('M'));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function post(array $fields): Request
    {
        return Request::create('POST', '/account/registrati/', body: $fields);
    }
}
