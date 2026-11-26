<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Security;

use FourStreet\Http\Cookie;
use FourStreet\Http\CookiePolicy;
use FourStreet\Http\Origin;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfProtection;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Security\Token;
use FourStreet\Tests\Support\StubController;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsrfProtection::class)]
#[CoversClass(CsrfToken::class)]
#[UsesClass(Cookie::class)]
#[UsesClass(CookiePolicy::class)]
#[UsesClass(Origin::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
#[UsesClass(Token::class)]
final class CsrfProtectionTest extends TestCase
{
    private StubController $page;

    private StubController $rejected;

    private CsrfProtection $csrf;

    protected function setUp(): void
    {
        $this->page = new StubController('pagina');
        $this->rejected = new StubController(respond: static fn(Request $request): Response => Response::text('respinto', 403));
        $this->csrf = new CsrfProtection(new CookiePolicy(true), Origin::fromUrl('https://fourstreet.studio'), $this->rejected);
    }

    public function testAFirstVisitGetsATokenInTheRequestAndInACookie(): void
    {
        $response = $this->csrf->process(Request::create('GET', '/account/accedi/'), $this->page);

        self::assertCount(1, $this->page->requests);
        $token = CsrfToken::of($this->page->requests[0])->value;
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $token);
        self::assertSame('__Host-csrf=' . $token . '; Path=/; Secure; HttpOnly; SameSite=Lax', $response->header('Set-Cookie'));
    }

    public function testTheCookieTokenIsReused(): void
    {
        $token = Token::generate()->value;

        $response = $this->csrf->process(Request::create('GET', '/', cookies: ['__Host-csrf' => $token]), $this->page);

        self::assertCount(1, $this->page->requests);
        self::assertSame($token, CsrfToken::of($this->page->requests[0])->value);
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    public function testAMatchingFormPasses(): void
    {
        $token = Token::generate()->value;

        $response = $this->csrf->process(
            Request::create('POST', '/', ['Sec-Fetch-Site' => 'same-origin'], ['csrf' => $token], ['__Host-csrf' => $token]),
            $this->page,
        );

        self::assertSame('pagina', $response->body);
        self::assertSame([], $this->rejected->requests);
    }

    public function testAForgedCookieWithTheWrongShapeIsReplaced(): void
    {
        $response = $this->csrf->process(
            Request::create('POST', '/', ['Sec-Fetch-Site' => 'same-origin'], ['csrf' => 'x'], ['__Host-csrf' => 'x']),
            $this->page,
        );

        self::assertSame(403, $response->status);
        self::assertStringStartsWith('__Host-csrf=', (string) $response->header('Set-Cookie'));
        self::assertCount(1, $this->rejected->requests);
        self::assertNotSame('x', CsrfToken::of($this->rejected->requests[0])->value);
    }

    public function testWithoutTheMiddlewareThereIsNoToken(): void
    {
        $this->expectException(LogicException::class);

        CsrfToken::of(Request::create('GET', '/'));
    }
}
