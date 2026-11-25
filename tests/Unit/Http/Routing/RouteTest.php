<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http\Routing;

use FourStreet\Http\Routing\Route;
use FourStreet\Tests\Support\StubController;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Route::class)]
final class RouteTest extends TestCase
{
    public function testGetRoutesAlsoAnswerHead(): void
    {
        $route = Route::get('/links/', new StubController());

        self::assertSame(['GET', 'HEAD'], $route->methods);
        self::assertTrue($route->allows('GET'));
        self::assertTrue($route->allows('HEAD'));
        self::assertFalse($route->allows('POST'));
    }

    public function testMethodsAreNormalised(): void
    {
        self::assertSame(['POST', 'PUT'], Route::create(['post', 'PUT', 'post'], '/x', new StubController())->methods);
        self::assertSame(['HEAD', 'GET'], Route::create(['head', 'get'], '/x', new StubController())->methods);
    }

    public function testExactPathsMatchOnlyThemselves(): void
    {
        $route = Route::get('/calendario.php', new StubController());

        self::assertSame([], $route->matchPath('/calendario.php'));
        self::assertNull($route->matchPath('/calendarioXphp'));
        self::assertNull($route->matchPath('/calendario.php/'));
        self::assertNull($route->matchPath('/CALENDARIO.PHP'));
    }

    public function testParametersAreExtracted(): void
    {
        $route = Route::get('/corsi/{slug}/turni/{day}', new StubController());

        self::assertSame(['slug' => 'yoga', 'day' => 'lunedì'], $route->matchPath('/corsi/yoga/turni/lunedì'));
        self::assertNull($route->matchPath('/corsi/yoga/turni/'));
        self::assertNull($route->matchPath('/corsi/yoga/extra/turni/lunedì'), 'Un parametro non attraversa le barre.');
        self::assertNull($route->matchPath('/corsi/yoga/turni/lunedì/'));
    }

    public function testLiteralPartsAreNotRegularExpressions(): void
    {
        $route = Route::get('/file/{name}.ics', new StubController());

        self::assertSame(['name' => 'evento'], $route->matchPath('/file/evento.ics'));
        self::assertNull($route->matchPath('/file/eventoXics'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPatterns(): iterable
    {
        yield 'senza barra iniziale' => ['links/'];
        yield 'parametro ripetuto' => ['/{id}/{id}'];
        yield 'graffa non chiusa' => ['/corsi/{slug'];
        yield 'nome non valido' => ['/corsi/{1slug}'];
    }

    #[DataProvider('invalidPatterns')]
    public function testRejectsInvalidPatterns(string $pattern): void
    {
        $this->expectException(InvalidArgumentException::class);

        Route::get($pattern, new StubController());
    }
}
