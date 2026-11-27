<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Admin;

use FourStreet\Account\AccountModule;
use FourStreet\Admin\AdminModule;
use FourStreet\Asset\AssetUrls;
use FourStreet\Backend;
use FourStreet\Http\Origin;
use FourStreet\Http\Request;
use FourStreet\Http\Routing\Route;
use FourStreet\Mail\Outbox;
use FourStreet\Security\Password\NativePasswordHasher;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\StubController;
use FourStreet\View\Icons;
use FourStreet\View\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminModule::class)]
final class AdminModuleTest extends TestCase
{
    public function testTheUnavailableRoutesHaveTheSameAddressesAndMethods(): void
    {
        $page = new StubController();

        self::assertSame(self::expected(), self::table(AdminModule::unavailableRoutes($page)));
        foreach (AdminModule::unavailableRoutes($page) as $route) {
            $response = ($route->controller)(Request::create('GET', $route->pattern));
            self::assertSame('no-store', $response->header('Cache-Control'));
        }
        self::assertCount(count(AdminModule::ENDPOINTS), $page->requests);
    }

    public function testTheRealRoutesFollowTheSameTable(): void
    {
        $fixture = new AccountFixture();
        $backend = new Backend($fixture->database, $fixture->mailer, NativePasswordHasher::bcrypt(4), Origin::fromUrl(AccountFixture::ORIGIN), str_repeat('k', 32));
        $view = new View(SiteFixture::templateDirectory(), new AssetUrls(SiteFixture::PUBLIC_DIR, SiteFixture::mtimes()), new Icons());
        $account = AccountModule::create($backend, $view, SiteFixture::config(), $fixture->clock, $fixture->reporter, new Outbox($fixture->mailer, $fixture->reporter), new StubController(), new StubController());

        self::assertSame(self::expected(), self::table(AdminModule::create($backend, $view, SiteFixture::config(), $fixture->clock, $fixture->reporter, $account->guards())->routes()));
    }

    /**
     * @return list<array{string, list<string>}>
     */
    private static function expected(): array
    {
        $expected = [];
        foreach (AdminModule::ENDPOINTS as $path => $methods) {
            $expected[] = [$path, Route::create($methods, $path, new StubController())->methods];
        }

        return $expected;
    }

    /**
     * @param list<Route> $routes
     *
     * @return list<array{string, list<string>}>
     */
    private static function table(array $routes): array
    {
        return array_map(static fn(Route $route): array => [$route->pattern, $route->methods], $routes);
    }
}
