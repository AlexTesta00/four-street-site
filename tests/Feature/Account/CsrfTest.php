<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature\Account;

use FourStreet\Application;
use FourStreet\Controller\FormExpiredController;
use FourStreet\Security\Csrf\CsrfProtection;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\TestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(CsrfProtection::class)]
#[CoversClass(FormExpiredController::class)]
final class CsrfTest extends TestCase
{
    private const array REGISTRATION = [
        'nome' => AccountFixture::NAME,
        'email' => AccountFixture::EMAIL,
        'password' => AccountFixture::PASSWORD,
        'privacy' => '1',
    ];

    private AccountFixture $fixture;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->fixture = new AccountFixture();
        $this->browser = $this->fixture->browser();
        $this->browser->get('/account/registrati/');
    }

    public function testTheTokenCookieIsSetOnceAndReused(): void
    {
        $token = $this->browser->csrfToken();
        self::assertNotNull($token);
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $token);

        $again = $this->browser->get('/account/accedi/');

        self::assertSame([], TestBrowser::setCookieHeaders($again));
        self::assertStringContainsString('name="csrf" value="' . $token . '"', $again->body);
    }

    public function testTheCookieIsSecureWithTheHostPrefix(): void
    {
        $form = $this->fixture->browser('198.51.100.1')->get('/account/accedi/');

        self::assertMatchesRegularExpression('/\A__Host-csrf=[A-Za-z0-9_-]{43}; Path=\/; Secure; HttpOnly; SameSite=Lax\z/', TestBrowser::setCookieHeaders($form)[0] ?? '');
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, string>}>
     */
    public static function forgedRequests(): iterable
    {
        yield 'senza token' => [['csrf' => ''], ['Sec-Fetch-Site' => 'same-origin']];
        yield 'token diverso' => [['csrf' => str_repeat('B', 43)], ['Sec-Fetch-Site' => 'same-origin']];
        yield 'da un altro sito' => [[], ['Sec-Fetch-Site' => 'cross-site']];
        yield 'da un sottodominio' => [[], ['Sec-Fetch-Site' => 'same-site']];
        yield 'origine diversa, browser senza Sec-Fetch-Site' => [[], ['Origin' => 'https://evil.example']];
        yield 'origine nulla' => [[], ['Origin' => 'null']];
        yield 'stesso host ma in http' => [[], ['Origin' => 'http://fourstreet.studio']];
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $headers
     */
    #[DataProvider('forgedRequests')]
    public function testForgedFormsAreRejectedBeforeAnythingHappens(array $fields, array $headers): void
    {
        $response = $this->browser->post('/account/registrati/', [...self::REGISTRATION, ...$fields], $headers);

        self::assertSame(403, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString('Il modulo è scaduto.</h1>', $response->body);
        self::assertStringContainsString('<a href="/account/registrati/" class="fs-btn fs-btn--primary fs-btn--block">Ricarica e riprova</a>', $response->body);
        self::assertSame(0, $this->fixture->count('users'));
        self::assertSame([], $this->fixture->mailer->sent);
    }

    public function testWithoutTheCookieTheFormIsRejectedAndTheCookieArrives(): void
    {
        $fresh = $this->fixture->browser('198.51.100.2');

        $response = $fresh->post('/account/registrati/', [...self::REGISTRATION, 'csrf' => str_repeat('C', 43)]);

        self::assertSame(403, $response->status);
        self::assertNotNull($fresh->csrfToken(), 'Il 403 porta il cookie: ricaricando la pagina il modulo funziona.');
        self::assertSame(303, $fresh->post('/account/registrati/', self::REGISTRATION)->status);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function acceptedOrigins(): iterable
    {
        yield 'stessa origine' => [['Sec-Fetch-Site' => 'same-origin']];
        yield 'aperto dalla persona' => [['Sec-Fetch-Site' => 'none']];
        yield 'browser vecchio, stessa origine' => [['Origin' => 'https://fourstreet.studio']];
        yield 'browser vecchio, nessuna origine' => [[]];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('acceptedOrigins')]
    public function testFormsFromThisSiteAreAccepted(array $headers): void
    {
        self::assertSame(303, $this->browser->post('/account/registrati/', self::REGISTRATION, $headers)->status);
    }
}
