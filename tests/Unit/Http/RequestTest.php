<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http;

use ArrayObject;
use FourStreet\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function uris(): iterable
    {
        yield 'radice' => ['/', '/', ''];
        yield 'con query' => ['/?anteprima=zero', '/', 'anteprima=zero'];
        yield 'pagina' => ['/links/', '/links/', ''];
        yield 'percorso codificato' => ['/calendario%2Ephp', '/calendario.php', ''];
        yield 'barre ripetute' => ['//links//', '/links/', ''];
        yield 'forma assoluta' => ['https://fourstreet.studio/links/?x=1', '/links/', 'x=1'];
        yield 'frammento ignorato' => ['/links/#inizio', '/links/', ''];
        yield 'vuoto' => ['', '/', ''];
        yield 'punto interrogativo nella query' => ['/?a=1?b=2', '/', 'a=1?b=2'];
    }

    #[DataProvider('uris')]
    public function testSplitsPathAndQueryString(string $uri, string $path, string $queryString): void
    {
        $request = Request::create('GET', $uri);

        self::assertSame($path, $request->path);
        self::assertSame($queryString, $request->queryString);
    }

    public function testMethodIsUppercase(): void
    {
        self::assertSame('POST', Request::create('post', '/')->method);
    }

    public function testQueryParametersAreReadAsText(): void
    {
        $request = Request::create('GET', '/?anteprima=mappa&lista[]=1&vuoto=');

        self::assertSame('mappa', $request->queryParam('anteprima'));
        self::assertSame('', $request->queryParam('vuoto'));
        self::assertNull($request->queryParam('lista'), 'Un array non è un valore testuale.');
        self::assertNull($request->queryParam('manca'));
    }

    public function testHeadersAreCaseInsensitive(): void
    {
        $request = Request::create('GET', '/', ['Accept-Language' => 'it-IT']);

        self::assertSame('it-IT', $request->header('accept-language'));
        self::assertSame('it-IT', $request->header('ACCEPT-LANGUAGE'));
        self::assertNull($request->header('Cookie'));
    }

    public function testFromGlobals(): void
    {
        $request = Request::fromGlobals([
            'REQUEST_METHOD' => 'HEAD',
            'REQUEST_URI' => '/links/?anteprima=zero',
            'HTTP_ACCEPT_LANGUAGE' => 'it',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'CONTENT_TYPE' => 'text/plain',
            'CONTENT_LENGTH' => '0',
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_TIME' => 1_764_000_000,
            0 => 'argv',
        ]);

        self::assertSame('HEAD', $request->method);
        self::assertSame('/links/', $request->path);
        self::assertSame('zero', $request->queryParam('anteprima'));
        self::assertSame('it', $request->header('Accept-Language'));
        self::assertSame('https', $request->header('X-Forwarded-Proto'));
        self::assertSame('text/plain', $request->header('Content-Type'));
        self::assertSame('0', $request->header('Content-Length'));
        self::assertNull($request->header('Script-Name'));
    }

    public function testFromGlobalsWithoutRequestData(): void
    {
        $request = Request::fromGlobals(['REQUEST_METHOD' => ['GET'], 'REQUEST_URI' => null]);

        self::assertSame('GET', $request->method);
        self::assertSame('/', $request->path);
    }

    public function testRouteAttributesDoNotChangeTheOriginal(): void
    {
        $request = Request::create('GET', '/corsi/yoga');
        $withAttributes = $request->withAttributes(['slug' => 'yoga']);

        self::assertNull($request->attribute('slug'));
        self::assertSame('yoga', $withAttributes->attribute('slug'));
        self::assertSame('/corsi/yoga', $withAttributes->path);
        self::assertSame('b', $withAttributes->withAttributes(['slug' => 'b'])->attribute('slug'));
    }

    public function testPathWithTheSameQueryString(): void
    {
        self::assertSame('/links/?anteprima=zero', Request::create('GET', '/links?anteprima=zero')->pathWithQuery('/links/'));
        self::assertSame('/links/', Request::create('GET', '/links')->pathWithQuery('/links/'));
    }

    public function testFormFieldsAreReadAsText(): void
    {
        $request = Request::create('POST', '/account/accedi/', body: ['email' => 'a@b.it', 'privacy' => '1', 'lista' => ['x'], 'vuoto' => '']);

        self::assertSame('a@b.it', $request->bodyParam('email'));
        self::assertSame('', $request->bodyParam('vuoto'));
        self::assertNull($request->bodyParam('lista'), 'Un array non è un valore testuale.');
        self::assertNull($request->bodyParam('manca'));
        self::assertNull($request->queryParam('email'), 'I campi del modulo non sono la query string.');
    }

    public function testCookiesAndTheRemoteAddress(): void
    {
        $request = Request::create('GET', '/', cookies: ['__Host-session' => 'abc', 'a' => ['b' => 'c'], 7 => 'x'], remoteAddress: '203.0.113.7');

        self::assertSame('abc', $request->cookie('__Host-session'));
        self::assertNull($request->cookie('a'), 'Un cookie diventato array non vale.');
        self::assertNull($request->cookie('7'));
        self::assertSame('203.0.113.7', $request->remoteAddress);
        self::assertSame('', Request::create('GET', '/')->remoteAddress);
    }

    public function testFromGlobalsWithTheFormCookiesAndAddress(): void
    {
        $request = Request::fromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/account/accedi/', 'REMOTE_ADDR' => '2001:db8::1'],
            ['email' => 'a@b.it'],
            ['csrf' => 'token'],
        );

        self::assertSame('a@b.it', $request->bodyParam('email'));
        self::assertSame('token', $request->cookie('csrf'));
        self::assertSame('2001:db8::1', $request->remoteAddress);
        self::assertSame('', Request::fromGlobals(['REMOTE_ADDR' => ['x']])->remoteAddress);
    }

    public function testContextObjectsTravelWithTheRequest(): void
    {
        $request = Request::create('GET', '/');
        $marker = new ArrayObject(['visitatore']);
        $withContext = $request->withContext($marker)->withAttributes(['slug' => 'x']);

        self::assertNull($request->context(ArrayObject::class));
        self::assertSame($marker, $withContext->context(ArrayObject::class));
        self::assertSame('x', $withContext->attribute('slug'));
        self::assertSame($marker, $withContext->withAttributes([])->context(ArrayObject::class));
    }

    public function testSafeMethods(): void
    {
        self::assertTrue(Request::create('GET', '/')->isSafe());
        self::assertTrue(Request::create('head', '/')->isSafe());
        self::assertFalse(Request::create('POST', '/')->isSafe());
        self::assertFalse(Request::create('DELETE', '/')->isSafe());
    }
}
