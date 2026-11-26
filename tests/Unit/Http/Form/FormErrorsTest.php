<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http\Form;

use FourStreet\Http\Form\FormErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormErrors::class)]
final class FormErrorsTest extends TestCase
{
    public function testCollectsOneMessagePerFieldInOrder(): void
    {
        $empty = new FormErrors();
        $errors = $empty
            ->with('email', 'Scrivi la tua email.')
            ->with('password', 'Scrivi la password.')
            ->with('email', 'Un secondo errore sulla stessa email.');

        self::assertTrue($empty->isEmpty());
        self::assertFalse($errors->isEmpty());
        self::assertSame(['email' => 'Scrivi la tua email.', 'password' => 'Scrivi la password.'], $errors->all());
        self::assertTrue($errors->has('password'));
        self::assertFalse($errors->has('nome'));
        self::assertSame('Scrivi la tua email.', $errors->get('email'));
        self::assertNull($errors->get('nome'));
    }
}
