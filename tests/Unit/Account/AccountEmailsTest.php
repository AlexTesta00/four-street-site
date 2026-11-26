<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Account;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Account\AccountEmails;
use FourStreet\Account\AccountUrls;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\Role;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Account\User;
use FourStreet\Asset\AssetUrls;
use FourStreet\Http\Origin;
use FourStreet\Mail\Mailbox;
use FourStreet\Mail\MailTemplates;
use FourStreet\Mail\Message;
use FourStreet\Security\Token;
use FourStreet\Tests\Support\InMemoryMtimeLookup;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Escaper;
use FourStreet\View\Html;
use FourStreet\View\Icons;
use FourStreet\View\Template;
use FourStreet\View\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountEmails::class)]
#[CoversClass(MailTemplates::class)]
#[UsesClass(AccountUrls::class)]
#[UsesClass(EmailAddress::class)]
#[UsesClass(ReturnPath::class)]
#[UsesClass(TokenPurpose::class)]
#[UsesClass(User::class)]
#[UsesClass(AssetUrls::class)]
#[UsesClass(Origin::class)]
#[UsesClass(Mailbox::class)]
#[UsesClass(Message::class)]
#[UsesClass(Token::class)]
#[UsesClass(ItalianDateFormatter::class)]
#[UsesClass(Escaper::class)]
#[UsesClass(Html::class)]
#[UsesClass(Icons::class)]
#[UsesClass(Template::class)]
#[UsesClass(View::class)]
final class AccountEmailsTest extends TestCase
{
    private const string TOKEN = 'TOKENtokenTOKENtokenTOKENtokenTOKENtoken123';

    private AccountEmails $emails;

    private User $user;

    protected function setUp(): void
    {
        $this->emails = new AccountEmails(
            new MailTemplates(new View(SiteFixture::templateDirectory(), new AssetUrls(SiteFixture::PUBLIC_DIR, new InMemoryMtimeLookup()), new Icons())),
            Origin::fromUrl('https://fourstreet.studio'),
            SiteFixture::config(),
            new ItalianDateFormatter(),
        );
        $at = new DateTimeImmutable('2026-11-25 07:00:00', new DateTimeZone('UTC'));
        $email = EmailAddress::tryFrom('mario@example.com');
        self::assertNotNull($email);
        $this->user = new User(7, 'Mario <b>Rossi</b>', $email, 'hash', $at, $at, Role::Customer, $at, $at);
    }

    public function testTheVerificationEmail(): void
    {
        $message = $this->emails->verification($this->user, self::token());

        self::assertSame('Conferma la tua email', $message->subject);
        self::assertSame('mario@example.com', $message->to->email);
        self::assertSame('Mario <b>Rossi</b>', $message->to->name);
        self::assertStringContainsString("Ciao Mario <b>Rossi</b>,\n\nconferma la tua email", $message->text);
        self::assertStringContainsString("\n\nhttps://fourstreet.studio/account/verifica/?token=" . self::TOKEN . "\n\n", $message->text);
        self::assertStringContainsString('Il link vale 48 ore', $message->text);
        self::assertStringStartsWith("4STREET\n\nCiao", $message->text);
        self::assertStringEndsWith("--\n4Street · Via del Bando 22, San Marino\nhttps://fourstreet.studio\n", $message->text);
        $this->assertSafeHtml($message);
        self::assertStringContainsString('href="https://fourstreet.studio/account/verifica/?token=' . self::TOKEN . '"', (string) $message->html);
        self::assertStringContainsString('Ciao Mario &lt;b&gt;Rossi&lt;/b&gt;,', (string) $message->html);
        self::assertStringContainsString('>Conferma l&#039;email</a>', (string) $message->html);
    }

    public function testTheAlreadyRegisteredEmailKeepsTheDestination(): void
    {
        $message = $this->emails->alreadyRegistered($this->user, ReturnPath::fromInput('/prenota/'));

        self::assertSame('Hai già un account 4Street', $message->subject);
        self::assertStringContainsString('https://fourstreet.studio/account/accedi/?ritorno=/prenota/', $message->text);
        self::assertStringContainsString('https://fourstreet.studio/account/password-dimenticata/?ritorno=/prenota/', $message->text);
        $this->assertSafeHtml($message);
    }

    public function testThePasswordResetEmail(): void
    {
        $message = $this->emails->passwordReset($this->user, self::token());

        self::assertSame('Scegli una nuova password', $message->subject);
        self::assertStringContainsString('https://fourstreet.studio/account/nuova-password/?token=' . self::TOKEN, $message->text);
        self::assertStringContainsString('Il link vale un\'ora', $message->text);
        $this->assertSafeHtml($message);
    }

    public function testThePasswordChangedEmailUsesTheLocalTime(): void
    {
        $message = $this->emails->passwordChanged($this->user, new DateTimeImmutable('2026-12-06 14:05:00', new DateTimeZone('UTC')));

        self::assertSame('La tua password è cambiata', $message->subject);
        self::assertStringContainsString('è stata cambiata domenica 6 dicembre 2026 alle 15:05.', $message->text);
        self::assertStringContainsString('https://fourstreet.studio/account/password-dimenticata/', $message->text);
        $this->assertSafeHtml($message);
    }

    public function testAnUnconfirmedAccountIsGreetedWithoutItsName(): void
    {
        // Chiunque può registrare un indirizzo non suo: il nome scritto nel modulo non va nella casella di un altro.
        $at = new DateTimeImmutable('2026-11-25 07:00:00', new DateTimeZone('UTC'));
        $email = EmailAddress::tryFrom('anna@example.com');
        self::assertNotNull($email);
        $pending = new User(8, 'Scrivi a truffa.example', $email, 'hash', null, $at, Role::Customer, $at, $at, str_repeat('a', 64));

        foreach ([
            $this->emails->verification($pending, self::token()),
            $this->emails->passwordReset($pending, self::token()),
            $this->emails->passwordChanged($pending, $at),
        ] as $message) {
            self::assertSame('', $message->to->name, $message->subject);
            self::assertStringStartsWith("4STREET\n\nCiao,\n\n", $message->text);
            self::assertStringNotContainsString('truffa', $message->text);
            self::assertStringNotContainsString('truffa', (string) $message->html);
            self::assertStringContainsString('>Ciao,</p>', (string) $message->html);
        }
    }

    /**
     * L'HTML delle email: stili in linea, nessuna risorsa remota, nessuno script, nessun foglio di stile.
     */
    private function assertSafeHtml(Message $message): void
    {
        $html = (string) $message->html;

        self::assertStringStartsWith("<!doctype html>\n<html lang=\"it\">", $html);
        self::assertStringContainsString('<body style="margin:0;padding:0;background-color:#0b0b0a;">', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<link', $html);
        self::assertStringNotContainsString('<style', $html);
        self::assertStringNotContainsString('class=', $html);
        self::assertStringContainsString('4Street · Via del Bando 22, San Marino', $html);
    }

    private static function token(): Token
    {
        $token = Token::fromString(self::TOKEN);
        self::assertNotNull($token);

        return $token;
    }
}
