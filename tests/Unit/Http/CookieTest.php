<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http;

use FourStreet\Http\Cookie;
use FourStreet\Http\CookiePolicy;
use FourStreet\Http\Origin;
use FourStreet\Http\Request;
use FourStreet\Http\SameSite;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cookie::class)]
#[CoversClass(CookiePolicy::class)]
#[CoversClass(SameSite::class)]
#[UsesClass(Origin::class)]
#[UsesClass(Request::class)]
final class CookieTest extends TestCase
{
    public function testTheHeaderValue(): void
    {
        self::assertSame(
            '__Host-session=abc_-1; Max-Age=2592000; Path=/; Secure; HttpOnly; SameSite=Lax',
            (new Cookie('__Host-session', 'abc_-1', 2_592_000))->headerValue(),
        );
        self::assertSame(
            'csrf=x; Path=/; HttpOnly; SameSite=Strict',
            (new Cookie('csrf', 'x', secure: false, sameSite: SameSite::Strict))->headerValue(),
        );
        self::assertSame('a=1; Path=/conti; Secure; SameSite=Lax', (new Cookie('a', '1', httpOnly: false, path: '/conti'))->headerValue());
    }

    public function testAnExpiredCopyDeletesTheCookie(): void
    {
        $expired = (new Cookie('session', 'segreto', 3600, secure: false))->expired();

        self::assertSame('session=; Max-Age=0; Path=/; HttpOnly; SameSite=Lax', $expired->headerValue());
    }

    /**
     * @return iterable<string, array{string, string, int|null, bool, string}>
     */
    public static function invalidCookies(): iterable
    {
        yield 'nome con spazio' => ['un nome', 'x', null, true, '/'];
        yield 'nome con uguale' => ['a=b', 'x', null, true, '/'];
        yield 'valore con punto e virgola' => ['a', 'x; Domain=evil', null, true, '/'];
        yield 'valore con a capo' => ['a', "x\r\nSet-Cookie: b=1", null, true, '/'];
        yield 'valore con spazio' => ['a', 'x y', null, true, '/'];
        yield 'durata negativa' => ['a', 'x', -1, true, '/'];
        yield 'percorso relativo' => ['a', 'x', null, true, 'conti'];
        yield 'percorso con punto e virgola' => ['a', 'x', null, true, '/;Domain=evil'];
        yield '__Host- senza Secure' => ['__Host-a', 'x', null, false, '/'];
        yield '__Host- fuori dalla radice' => ['__Host-a', 'x', null, true, '/conti'];
    }

    #[DataProvider('invalidCookies')]
    public function testRejectsCookiesThatCouldInjectOrLeak(string $name, string $value, ?int $maxAge, bool $secure, string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cookie($name, $value, $maxAge, $secure, path: $path);
    }

    public function testThePolicyFollowsThePublicOrigin(): void
    {
        $production = CookiePolicy::for(Origin::fromUrl('https://fourstreet.studio'));
        $development = CookiePolicy::for(Origin::fromUrl('http://localhost:8000'));

        self::assertSame('__Host-session', $production->name('session'));
        self::assertSame('__Host-session=t; Max-Age=60; Path=/; Secure; HttpOnly; SameSite=Lax', $production->cookie('session', 't', 60)->headerValue());
        self::assertSame('__Host-session=; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Lax', $production->expired('session')->headerValue());
        self::assertSame('session', $development->name('session'));
        self::assertSame('session=t; Path=/; HttpOnly; SameSite=Lax', $development->cookie('session', 't')->headerValue());
    }

    public function testThePolicyReadsItsOwnCookieName(): void
    {
        $request = Request::create('GET', '/', cookies: ['__Host-session' => 'sicuro', 'session' => 'insicuro']);

        self::assertSame('sicuro', (new CookiePolicy(true))->read($request, 'session'));
        self::assertSame('insicuro', (new CookiePolicy(false))->read($request, 'session'));
        self::assertNull((new CookiePolicy(true))->read($request, 'csrf'));
    }
}
