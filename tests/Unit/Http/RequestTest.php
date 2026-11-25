<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http;

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
}
