<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http;

use FourStreet\Http\Response;
use FourStreet\Tests\Support\RecordingHeaderWriter;
use FourStreet\View\Html;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
#[UsesClass(Html::class)]
final class ResponseTest extends TestCase
{
    public function testDefaults(): void
    {
        $response = new Response();

        self::assertSame(200, $response->status);
        self::assertSame([], $response->headers());
        self::assertSame('', $response->body);
    }

    public function testHtmlTextAndRedirectResponses(): void
    {
        $html = Response::html(new Html('<p>ciao</p>'), 404);
        self::assertSame(404, $html->status);
        self::assertSame('text/html; charset=utf-8', $html->header('Content-Type'));
        self::assertSame('<p>ciao</p>', $html->body);

        $text = Response::text('Metodo non consentito.', 405);
        self::assertSame('text/plain; charset=utf-8', $text->header('content-type'));

        $redirect = Response::redirect('/links/');
        self::assertSame(301, $redirect->status);
        self::assertSame('/links/', $redirect->header('Location'));
        self::assertSame('', $redirect->body);
    }

    public function testHeaderNamesAreCaseInsensitiveAndKeepTheirSpelling(): void
    {
        $response = (new Response(200, ['X-Test' => 'a']))->withHeader('x-test', 'b');

        self::assertTrue($response->hasHeader('X-TEST'));
        self::assertSame('b', $response->header('X-Test'));
        self::assertSame(['x-test' => ['b']], $response->headers());
        self::assertFalse($response->hasHeader('X-Other'));
        self::assertNull($response->header('X-Other'));
    }

    public function testHeadersWithSeveralValues(): void
    {
        $response = (new Response())
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('set-cookie', 'b=2');

        self::assertSame(['Set-Cookie' => ['a=1', 'b=2']], $response->headers());
        self::assertSame('a=1, b=2', $response->header('Set-Cookie'));
        self::assertSame(['Vary' => ['Accept', 'Cookie']], (new Response(200, ['Vary' => ['Accept', 'Cookie']]))->headers());
    }

    public function testWithMethodsReturnNewResponses(): void
    {
        $original = new Response(200, ['X-Test' => 'a'], 'corpo');
        $changed = $original->withHeader('X-Other', 'b');
        $withoutBody = $original->withoutBody();

        self::assertFalse($original->hasHeader('X-Other'));
        self::assertTrue($changed->hasHeader('X-Other'));
        self::assertSame('corpo', $original->body);
        self::assertSame('', $withoutBody->body);
        self::assertSame($original->headers(), $withoutBody->headers());
        self::assertSame(200, $withoutBody->status);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidStatuses(): iterable
    {
        yield 'troppo basso' => [99];
        yield 'troppo alto' => [600];
    }

    #[DataProvider('invalidStatuses')]
    public function testRejectsInvalidStatuses(int $status): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response($status);
    }

    public function testRedirectsNeedARedirectStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::redirect('/', 200);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidHeaders(): iterable
    {
        yield 'a capo nel valore' => ['Location', "/\r\nSet-Cookie: x=1"];
        yield 'solo LF nel valore' => ['X-Test', "a\nb"];
        yield 'NUL nel valore' => ['X-Test', "a\0b"];
        yield 'spazio nel nome' => ['X Test', 'a'];
        yield 'due punti nel nome' => ['X-Test:', 'a'];
        yield 'nome vuoto' => ['', 'a'];
    }

    #[DataProvider('invalidHeaders')]
    public function testRejectsHeaderInjection(string $name, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Response())->withHeader($name, $value);
    }

    public function testSendWritesStatusHeadersAndBody(): void
    {
        $writer = new RecordingHeaderWriter();
        $response = (new Response(404, ['Content-Type' => 'text/html; charset=utf-8'], '<p>404</p>'))
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');

        $this->expectOutputString('<p>404</p>');
        $response->send($writer);

        self::assertSame(['X-Powered-By'], $writer->removed);
        self::assertSame(404, $writer->status);
        self::assertSame([
            ['line' => 'Content-Type: text/html; charset=utf-8', 'replace' => true],
            ['line' => 'Set-Cookie: a=1', 'replace' => true],
            ['line' => 'Set-Cookie: b=2', 'replace' => false],
        ], $writer->headers);
    }

    public function testSendAfterOutputHasStartedOnlyWritesTheBody(): void
    {
        $writer = new RecordingHeaderWriter(alreadySent: true);

        $this->expectOutputString('corpo');
        (new Response(500, ['X-Test' => 'a'], 'corpo'))->send($writer);

        self::assertNull($writer->status);
        self::assertSame([], $writer->headers);
        self::assertSame([], $writer->removed);
    }
}
