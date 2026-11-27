<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Account;

use FourStreet\Account\AccountGuards;
use FourStreet\Account\AccountModule;
use FourStreet\Account\LoginResult;
use FourStreet\Account\Session\RequireAdmin;
use FourStreet\Account\Session\RequireLogin;
use FourStreet\Account\Session\StartSession;
use FourStreet\Account\Session\Visitor;
use FourStreet\Account\VerificationResult;
use FourStreet\Asset\AssetUrls;
use FourStreet\Backend;
use FourStreet\Http\Middleware\NoReferrer;
use FourStreet\Http\Middleware\PrivatePage;
use FourStreet\Http\Origin;
use FourStreet\Http\Request;
use FourStreet\Http\Routing\Route;
use FourStreet\Mail\Outbox;
use FourStreet\Security\Csrf\CsrfProtection;
use FourStreet\Security\Password\NativePasswordHasher;
use FourStreet\Tests\Support\AccountFixture;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\StubController;
use FourStreet\View\Icons;
use FourStreet\View\View;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountModule::class)]
#[CoversClass(AccountGuards::class)]
#[CoversClass(Visitor::class)]
#[CoversClass(VerificationResult::class)]
#[CoversClass(LoginResult::class)]
final class AccountModuleTest extends TestCase
{
    public function testTheUnavailableRoutesHaveTheSameAddressesAndMethods(): void
    {
        $page = new StubController();

        self::assertSame(self::expected(), self::table(AccountModule::unavailableRoutes($page)));
        foreach (AccountModule::unavailableRoutes($page) as $route) {
            ($route->controller)(Request::create('GET', $route->pattern));
        }
        self::assertCount(count(AccountModule::ENDPOINTS), $page->requests);
    }

    public function testTheRealRoutesFollowTheSameTable(): void
    {
        self::assertSame(self::expected(), self::table(self::module()->routes()));
    }

    public function testTheGuardsForOtherModules(): void
    {
        $guards = self::module()->guards();

        self::assertSame([PrivatePage::class, CsrfProtection::class, StartSession::class], array_map(get_class(...), $guards->page()));
        self::assertSame([PrivatePage::class, CsrfProtection::class, StartSession::class, RequireLogin::class], array_map(get_class(...), $guards->member()));
        self::assertSame([NoReferrer::class, PrivatePage::class, CsrfProtection::class, StartSession::class], array_map(get_class(...), $guards->tokenPage()));
        self::assertSame([PrivatePage::class, CsrfProtection::class, StartSession::class, RequireLogin::class, RequireAdmin::class], array_map(get_class(...), $guards->admin()));
    }

    public function testVisitorsAndResultsRefuseImpossibleQuestions(): void
    {
        $guest = Visitor::guest();

        self::assertFalse($guest->isLoggedIn());
        foreach ([
            static fn(): mixed => $guest->user(),
            static fn(): mixed => Visitor::of(Request::create('GET', '/')),
            static fn(): mixed => VerificationResult::invalid()->user(),
            static fn(): mixed => LoginResult::wrongCredentials()->user(),
        ] as $question) {
            try {
                $question();
                self::fail('Doveva essere un errore di programmazione.');
            } catch (LogicException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
    }

    /** Il modulo come lo costruisce Application, con le dipendenze di prova. */
    private static function module(): AccountModule
    {
        $fixture = new AccountFixture();

        return AccountModule::create(
            new Backend($fixture->database, $fixture->mailer, NativePasswordHasher::bcrypt(4), Origin::fromUrl(AccountFixture::ORIGIN), str_repeat('k', 32)),
            new View(SiteFixture::templateDirectory(), new AssetUrls(SiteFixture::PUBLIC_DIR, SiteFixture::mtimes()), new Icons()),
            SiteFixture::config(),
            $fixture->clock,
            $fixture->reporter,
            new Outbox($fixture->mailer, $fixture->reporter),
            new StubController(),
            new StubController('pagina non trovata'),
        );
    }

    /**
     * La tabella degli indirizzi, con i metodi come li completa Route (GET porta con sé HEAD).
     *
     * @return list<array{string, list<string>}>
     */
    private static function expected(): array
    {
        $expected = [];
        foreach (AccountModule::ENDPOINTS as $path => $methods) {
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
