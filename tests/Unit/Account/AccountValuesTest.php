<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Account;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\Notice;
use FourStreet\Account\PasswordPolicy;
use FourStreet\Account\ReturnPath;
use FourStreet\Http\Request;
use FourStreet\Mail\Mailbox;
use FourStreet\Security\Password\NativePasswordHasher;
use FourStreet\Security\Token;
use FourStreet\View\Alert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddress::class)]
#[CoversClass(FullName::class)]
#[CoversClass(PasswordPolicy::class)]
#[CoversClass(ReturnPath::class)]
#[CoversClass(AccountUrls::class)]
#[CoversClass(Notice::class)]
#[CoversClass(Alert::class)]
#[UsesClass(Mailbox::class)]
#[UsesClass(NativePasswordHasher::class)]
#[UsesClass(Request::class)]
#[UsesClass(Token::class)]
final class AccountValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function emails(): iterable
    {
        yield 'normale' => ['mario@example.com', 'mario@example.com'];
        yield 'maiuscole e spazi' => ['  Mario.Rossi@Example.COM ', 'mario.rossi@example.com'];
        yield 'con il più' => ['mario+palestra@example.com', 'mario+palestra@example.com'];
        yield 'vuota' => ['', null];
        yield 'senza dominio' => ['mario@', null];
        yield 'senza chiocciola' => ['mario.example.com', null];
        yield 'con uno spazio' => ['mario rossi@example.com', null];
        yield 'troppo lunga' => [str_repeat('a', 64) . '@' . str_repeat('b', 186) . '.com', null];
    }

    #[DataProvider('emails')]
    public function testEmailsAreNormalized(string $input, ?string $expected): void
    {
        self::assertSame($expected, EmailAddress::tryFrom($input)?->value);
    }

    public function testAnEmailBecomesAMailbox(): void
    {
        $mailbox = EmailAddress::tryFrom('mario@example.com')?->mailbox('Mario Rossi');

        self::assertSame('mario@example.com', $mailbox?->email);
        self::assertSame('Mario Rossi', $mailbox->name);
        self::assertSame('mario@example.com', (string) EmailAddress::tryFrom('mario@example.com'));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function names(): iterable
    {
        yield 'nome e cognome' => ['Mario Rossi', 'Mario Rossi'];
        yield 'spazi in ordine' => ["  Maria   Grazia\tD'Angelo ", "Maria Grazia D'Angelo"];
        yield 'accenti e trattini' => ['Zoë Müller-Lüdenscheidt', 'Zoë Müller-Lüdenscheidt'];
        yield 'una lettera sola' => ['M', null];
        yield 'solo spazi' => ['   ', null];
        yield 'troppo lungo' => [str_repeat('a', 101), null];
        yield 'caratteri invisibili' => ["Mario\u{200B}Rossi", null];
    }

    #[DataProvider('names')]
    public function testFullNames(string $input, ?string $expected): void
    {
        self::assertSame($expected, FullName::tryFrom($input)?->value);
    }

    /**
     * @return iterable<string, array{string, int|null, string|null}>
     */
    public static function passwords(): iterable
    {
        yield 'otto caratteri' => ['12345678', null, null];
        yield 'una frase' => ['cavallo batteria graffetta', null, null];
        yield 'vuota' => ['', null, 'Scegli una password.'];
        yield 'sette caratteri' => ['1234567', null, 'La password deve avere almeno 8 caratteri.'];
        yield 'otto caratteri accentati' => ['èèèèèèèè', null, null];
        yield 'centoventotto caratteri' => [str_repeat('a', 128), null, null];
        yield 'centoventinove caratteri' => [str_repeat('a', 129), null, 'La password può avere al massimo 128 caratteri.'];
        yield 'oltre i 72 byte di bcrypt' => [str_repeat('è', 37), 72, 'La password è troppo lunga: accorciala un po\'.'];
        yield 'entro i 72 byte di bcrypt' => [str_repeat('è', 36), 72, null];
    }

    #[DataProvider('passwords')]
    public function testThePasswordRules(string $password, ?int $maxBytes, ?string $problem): void
    {
        self::assertSame($problem, (new PasswordPolicy($maxBytes))->problem($password));
    }

    public function testThePolicyFollowsTheHasher(): void
    {
        self::assertNotNull(PasswordPolicy::for(NativePasswordHasher::bcrypt(4))->problem(str_repeat('è', 37)));
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function destinations(): iterable
    {
        yield 'pagina interna' => ['/prenota/', '/prenota/'];
        yield 'con la query' => ['/prenota/?giorno=2026-12-08&fascia=18%3A30', '/prenota/?giorno=2026-12-08&fascia=18%3A30'];
        yield 'nessuna' => [null, '/account/'];
        yield 'vuota' => ['', '/account/'];
        yield 'altro sito' => ['https://evil.example/', '/account/'];
        yield 'senza schema' => ['//evil.example', '/account/'];
        yield 'barra rovesciata' => ['/\evil.example', '/account/'];
        yield 'barra rovesciata codificata come percorso' => ['/%5Cevil.example', '/%5Cevil.example'];
        yield 'spazi' => ['/prenota/ fascia', '/account/'];
        yield 'frammento' => ['/prenota/#fascia', '/account/'];
        yield 'punti' => ['/prenota/../account/', '/account/'];
        yield 'punti codificati' => ['/prenota/%2e%2e/account/', '/account/'];
        yield 'registrazione' => ['/account/registrati/', '/account/'];
        yield 'conferma' => ['/account/verifica/nuovo-link/', '/account/'];
        yield 'nuova password' => ['/account/nuova-password/?token=x', '/account/'];
        yield 'troppo lunga' => ['/' . str_repeat('a', 512), '/account/'];
    }

    #[DataProvider('destinations')]
    public function testOnlyCleanInternalDestinationsSurvive(?string $input, string $expected): void
    {
        self::assertSame($expected, ReturnPath::fromInput($input)->value);
    }

    public function testTheDestinationOfARequest(): void
    {
        self::assertSame('/prenota/caff%C3%A8/?a=1', ReturnPath::fromRequest(Request::create('GET', '/prenota/caff%C3%A8/?a=1'))->value);
        self::assertSame('/prenota/', ReturnPath::fromQuery(Request::create('GET', '/account/accedi/?ritorno=/prenota/'))->value);
        self::assertSame('/prenota/', ReturnPath::fromBody(Request::create('POST', '/account/accedi/', body: ['ritorno' => '/prenota/']))->value);
        self::assertTrue(ReturnPath::default()->isDefault());
        self::assertTrue(ReturnPath::fromInput('/account/')->isDefault());
    }

    public function testAccountAddresses(): void
    {
        $token = Token::fromString(str_repeat('t', 43));
        self::assertNotNull($token);

        self::assertSame('/account/accedi/', AccountUrls::to(AccountUrls::LOGIN));
        self::assertSame('/account/accedi/', AccountUrls::to(AccountUrls::LOGIN, ReturnPath::default()));
        self::assertSame('/account/accedi/?ritorno=/prenota/%3Fgiorno%3D8', AccountUrls::to(AccountUrls::LOGIN, ReturnPath::fromInput('/prenota/?giorno=8')));
        self::assertSame('/account/accedi/?avviso=uscito', AccountUrls::to(AccountUrls::LOGIN, notice: Notice::LoggedOut));
        self::assertSame('/account/?ritorno=/x/&avviso=uscito', AccountUrls::to(AccountUrls::HOME, ReturnPath::fromInput('/x/'), Notice::LoggedOut));
        self::assertSame('/account/verifica/?token=' . str_repeat('t', 43), AccountUrls::verify($token));
        self::assertSame('/account/nuova-password/?token=' . str_repeat('t', 43), AccountUrls::resetPassword($token));
    }

    public function testNoticesComeOnlyFromAFixedList(): void
    {
        self::assertSame(Notice::LoggedOut, Notice::fromRequest(Request::create('GET', '/?avviso=uscito')));
        self::assertNull(Notice::fromRequest(Request::create('GET', '/?avviso=%3Cscript%3E')));
        self::assertNull(Notice::fromRequest(Request::create('GET', '/')));

        foreach (Notice::cases() as $notice) {
            $alert = $notice->alert();
            self::assertNotSame('', $alert->message);
            self::assertSame($alert->tone === 'error' ? 'alert' : 'status', $alert->role());
        }
        self::assertSame('success', Alert::success('ok')->tone);
        self::assertSame('Titolo', Alert::info('ok', 'Titolo')->title);
    }
}
