<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Controller;

use FourStreet\Controller\RedirectController;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedirectController::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class RedirectControllerTest extends TestCase
{
    public function testRedirectsPermanentlyKeepingTheQueryString(): void
    {
        $response = (new RedirectController('/'))(Request::create('GET', '/index.php?anteprima=mappa'));

        self::assertSame(301, $response->status);
        self::assertSame('/?anteprima=mappa', $response->header('Location'));
    }

    public function testCustomStatus(): void
    {
        $response = (new RedirectController('/modulo/', 308))(Request::create('POST', '/modulo'));

        self::assertSame(308, $response->status);
        self::assertSame('/modulo/', $response->header('Location'));
    }
}
