<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit;

use FourStreet\Application;
use FourStreet\Controller\RedirectController;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Http\Routing\CanonicalRedirect;
use FourStreet\Http\Routing\MethodNotAllowed;
use FourStreet\Http\Routing\Route;
use FourStreet\Http\Routing\RouteFound;
use FourStreet\Http\Routing\RouteNotFound;
use FourStreet\Http\Routing\Router;
use FourStreet\Http\SecurityHeaders;
use FourStreet\Tests\Support\CollectingReporter;
use FourStreet\Tests\Support\StubController;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[UsesClass(RedirectController::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(RouteFound::class)]
#[UsesClass(RouteNotFound::class)]
#[UsesClass(MethodNotAllowed::class)]
#[UsesClass(CanonicalRedirect::class)]
#[UsesClass(SecurityHeaders::class)]
final class ApplicationTest extends TestCase
{
    private StubController $page;

    private StubController $notFound;

    private CollectingReporter $reporter;

    protected function setUp(): void
    {
        $this->page = new StubController('pagina');
        $this->notFound = new StubController(respond: static fn(Request $request): Response => Response::text('niente', 404));
        $this->reporter = new CollectingReporter();
    }

    public function testDispatchesToTheControllerWithTheRouteParameters(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/corsi/yoga'));

        self::assertSame(200, $response->status);
        self::assertSame('pagina', $response->body);
        self::assertCount(1, $this->page->requests);
        self::assertSame('yoga', $this->page->requests[0]->attribute('slug'));
    }

    public function testUnknownPathsGoToTheNotFoundController(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/nope'));

        self::assertSame(404, $response->status);
        self::assertSame('niente', $response->body);
        self::assertCount(1, $this->notFound->requests);
        self::assertSame('/nope', $this->notFound->requests[0]->path);
    }

    public function testMethodNotAllowedListsTheAllowedMethods(): void
    {
        $response = $this->application()->handle(Request::create('DELETE', '/corsi/yoga'));

        self::assertSame(405, $response->status);
        self::assertSame('GET, HEAD', $response->header('Allow'));
        self::assertSame('Metodo non consentito.', $response->body);
        self::assertSame([], $this->page->requests);
    }

    public function testCanonicalRedirectKeepsTheQueryString(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/corsi/yoga/turni?giorno=1'));

        self::assertSame(301, $response->status);
        self::assertSame('/corsi/yoga/turni/?giorno=1', $response->header('Location'));
    }

    public function testHeadReturnsTheHeadersWithoutTheBody(): void
    {
        $get = $this->application()->handle(Request::create('GET', '/corsi/yoga'));
        $head = $this->application()->handle(Request::create('HEAD', '/corsi/yoga'));

        self::assertSame(200, $head->status);
        self::assertSame('', $head->body);
        self::assertSame($get->headers(), $head->headers());
    }

    public function testEveryResponseGetsTheSecurityHeaders(): void
    {
        foreach (['/corsi/yoga', '/nope', '/corsi/yoga/turni', '/rotto'] as $path) {
            $response = $this->application()->handle(Request::create('GET', $path));

            self::assertSame('nosniff', $response->header('X-Content-Type-Options'), $path);
            self::assertTrue($response->hasHeader('Content-Security-Policy'), $path);
        }
    }

    public function testUnexpectedErrorsAreReportedAndHidden(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/rotto'));

        self::assertSame(500, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
        self::assertStringContainsString('Si è verificato un errore.', $response->body);
        self::assertStringNotContainsString('Segreto', $response->body);
        self::assertCount(1, $this->reporter->reported);
        self::assertSame('Segreto del server.', $this->reporter->reported[0]->getMessage());
    }

    private function application(): Application
    {
        $broken = new StubController(respond: static fn(Request $request): Response => throw new LogicException('Segreto del server.'));

        return new Application(
            new Router([
                Route::get('/corsi/{slug}', $this->page),
                Route::get('/corsi/{slug}/turni/', $this->page),
                Route::get('/rotto', $broken),
            ]),
            $this->notFound,
            SecurityHeaders::forSite(),
            $this->reporter,
        );
    }
}
