<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http\Middleware;

use ArrayObject;
use FourStreet\Http\Controller;
use FourStreet\Http\Middleware\Layer;
use FourStreet\Http\Middleware\Middleware;
use FourStreet\Http\Middleware\NoReferrer;
use FourStreet\Http\Middleware\PrivatePage;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Http\Routing\Route;
use FourStreet\Tests\Support\StubController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Layer::class)]
#[CoversClass(PrivatePage::class)]
#[CoversClass(NoReferrer::class)]
#[CoversClass(Route::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class MiddlewareTest extends TestCase
{
    public function testTheFirstMiddlewareSeesTheRequestFirstAndTheResponseLast(): void
    {
        /** @var ArrayObject<int, string> $trace */
        $trace = new ArrayObject();
        $tracer = static fn(string $name): Middleware => new readonly class ($name, $trace) implements Middleware {
            /**
             * @param ArrayObject<int, string> $trace
             */
            public function __construct(
                private string $name,
                private ArrayObject $trace,
            ) {}

            public function process(Request $request, Controller $next): Response
            {
                $this->trace->append('prima ' . $this->name);
                $response = $next($request);
                $this->trace->append('dopo ' . $this->name);

                return $response->withAddedHeader('X-Trace', $this->name);
            }
        };
        $controller = new StubController();

        $route = Route::get('/conti/', $controller)->through($tracer('esterno'), $tracer('interno'));
        $response = ($route->controller)(Request::create('GET', '/conti/'));

        self::assertSame(['prima esterno', 'prima interno', 'dopo interno', 'dopo esterno'], $trace->getArrayCopy());
        self::assertSame('interno, esterno', $response->header('X-Trace'));
        self::assertCount(1, $controller->requests);
        self::assertSame(['GET', 'HEAD'], $route->methods);
        self::assertSame('/conti/', $route->pattern);
    }

    public function testAMiddlewareCanAnswerOnItsOwn(): void
    {
        $controller = new StubController();
        $gate = new class implements Middleware {
            public function process(Request $request, Controller $next): Response
            {
                return Response::text('fermo qui', 403);
            }
        };

        $response = (new Layer($gate, $controller))(Request::create('GET', '/'));

        self::assertSame(403, $response->status);
        self::assertSame([], $controller->requests);
    }

    public function testAPrivatePageIsNeverCachedNorIndexed(): void
    {
        $controller = new StubController(respond: static fn(Request $request): Response => Response::html('<p>dati</p>')->withHeader('Cache-Control', 'public, max-age=60'));

        $response = (new Layer(new PrivatePage(), $controller))(Request::create('GET', '/account/'));

        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame('noindex', $response->header('X-Robots-Tag'));
    }

    public function testATokenPageSendsNoReferrer(): void
    {
        $response = (new Layer(new NoReferrer(), new StubController()))(Request::create('GET', '/account/verifica/?token=x'));

        self::assertSame('no-referrer', $response->header('Referrer-Policy'));
    }

    public function testPostAndFormRoutes(): void
    {
        $controller = new StubController();

        self::assertSame(['POST'], Route::post('/account/esci/', $controller)->methods);
        self::assertSame(['GET', 'POST', 'HEAD'], Route::form('/account/accedi/', $controller)->methods);
    }
}
