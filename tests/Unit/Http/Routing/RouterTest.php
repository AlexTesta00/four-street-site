<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http\Routing;

use FourStreet\Http\Routing\CanonicalRedirect;
use FourStreet\Http\Routing\MethodNotAllowed;
use FourStreet\Http\Routing\Route;
use FourStreet\Http\Routing\RouteFound;
use FourStreet\Http\Routing\RouteNotFound;
use FourStreet\Http\Routing\Router;
use FourStreet\Tests\Support\StubController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Router::class)]
#[CoversClass(RouteFound::class)]
#[CoversClass(RouteNotFound::class)]
#[CoversClass(MethodNotAllowed::class)]
#[CoversClass(CanonicalRedirect::class)]
#[UsesClass(Route::class)]
final class RouterTest extends TestCase
{
    public function testFindsTheControllerAndItsParameters(): void
    {
        $home = new StubController('home');
        $course = new StubController('corso');
        $router = new Router([Route::get('/', $home), Route::get('/corsi/{slug}', $course)]);

        $result = $router->match('GET', '/corsi/yoga');

        self::assertInstanceOf(RouteFound::class, $result);
        self::assertSame($course, $result->controller);
        self::assertSame(['slug' => 'yoga'], $result->parameters);

        $result = $router->match('HEAD', '/');
        self::assertInstanceOf(RouteFound::class, $result);
        self::assertSame($home, $result->controller);
    }

    public function testTheFirstMatchingRouteWins(): void
    {
        $exact = new StubController('esatta');
        $router = new Router([Route::get('/corsi/nuovo', $exact), Route::get('/corsi/{slug}', new StubController())]);

        $result = $router->match('GET', '/corsi/nuovo');

        self::assertInstanceOf(RouteFound::class, $result);
        self::assertSame($exact, $result->controller);
    }

    public function testUnknownPathsAreNotFound(): void
    {
        $router = new Router([Route::get('/', new StubController())]);

        self::assertInstanceOf(RouteNotFound::class, $router->match('GET', '/nope'));
        self::assertInstanceOf(RouteNotFound::class, (new Router([]))->match('GET', '/'));
    }

    public function testAKnownPathWithAnotherMethodIsNotAllowed(): void
    {
        $router = new Router([
            Route::get('/prenota', new StubController()),
            Route::create(['POST'], '/prenota', new StubController()),
            Route::create(['DELETE', 'POST'], '/prenota', new StubController()),
        ]);

        $result = $router->match('PUT', '/prenota');

        self::assertInstanceOf(MethodNotAllowed::class, $result);
        self::assertSame(['GET', 'HEAD', 'POST', 'DELETE'], $result->allowedMethods);
    }

    public function testRedirectsToTheCanonicalPathWithTheTrailingSlash(): void
    {
        $router = new Router([Route::get('/links/', new StubController()), Route::create(['POST'], '/modulo/', new StubController())]);

        $get = $router->match('GET', '/links');
        self::assertInstanceOf(CanonicalRedirect::class, $get);
        self::assertSame('/links/', $get->location);
        self::assertSame(301, $get->status);

        $head = $router->match('HEAD', '/links');
        self::assertInstanceOf(CanonicalRedirect::class, $head);
        self::assertSame(301, $head->status);

        // Con gli altri metodi il redirect deve conservare metodo e corpo.
        $post = $router->match('POST', '/modulo');
        self::assertInstanceOf(CanonicalRedirect::class, $post);
        self::assertSame(308, $post->status);
    }

    public function testTheCanonicalRedirectIsUrlEncoded(): void
    {
        $router = new Router([Route::get('/corsi/{slug}/', new StubController())]);

        $result = $router->match('GET', '/corsi/città e mare');

        self::assertInstanceOf(CanonicalRedirect::class, $result);
        self::assertSame('/corsi/citt%C3%A0%20e%20mare/', $result->location);
    }

    public function testNoRedirectWhenTheCanonicalPathDoesNotAcceptTheMethod(): void
    {
        $router = new Router([Route::get('/links/', new StubController())]);

        $result = $router->match('POST', '/links');

        self::assertInstanceOf(MethodNotAllowed::class, $result);
        self::assertSame(['GET', 'HEAD'], $result->allowedMethods);
    }

    public function testNoRedirectForPathsThatAlreadyEndWithASlashOrHaveNoCanonicalVersion(): void
    {
        $router = new Router([Route::get('/links/', new StubController()), Route::get('/calendario.php', new StubController())]);

        self::assertInstanceOf(RouteNotFound::class, $router->match('GET', '/calendario.php/'));
        self::assertInstanceOf(RouteNotFound::class, $router->match('GET', '/link'));
        self::assertInstanceOf(RouteNotFound::class, $router->match('GET', '/links//'));
    }
}
