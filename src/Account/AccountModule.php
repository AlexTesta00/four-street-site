<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Account\Http\AccountController;
use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Http\CheckEmailController;
use FourStreet\Account\Http\DashboardSection;
use FourStreet\Account\Http\ForgotPasswordController;
use FourStreet\Account\Http\LoginController;
use FourStreet\Account\Http\LogoutController;
use FourStreet\Account\Http\RegisterController;
use FourStreet\Account\Http\ResendVerificationController;
use FourStreet\Account\Http\ResetPasswordController;
use FourStreet\Account\Http\SignupBrowser;
use FourStreet\Account\Http\VerifyEmailController;
use FourStreet\Account\Session\PdoSessionRepository;
use FourStreet\Account\Session\RequireAdmin;
use FourStreet\Account\Session\RequireLogin;
use FourStreet\Account\Session\SessionManager;
use FourStreet\Account\Session\StartSession;
use FourStreet\Account\Token\AccountTokens;
use FourStreet\Account\Token\PdoAccountTokenRepository;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Backend;
use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Error\ExceptionReporter;
use FourStreet\Http\Controller;
use FourStreet\Http\CookiePolicy;
use FourStreet\Http\Middleware\NoReferrer;
use FourStreet\Http\Middleware\PrivatePage;
use FourStreet\Http\Routing\Route;
use FourStreet\Http\Routing\RouteProvider;
use FourStreet\Mail\MailTemplates;
use FourStreet\Mail\Outbox;
use FourStreet\Security\Csrf\CsrfProtection;
use FourStreet\Security\Fingerprinter;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\View;

/**
 * L'area clienti: costruisce e collega servizi, controller e rotte del modulo, e mette a disposizione
 * degli altri moduli i suoi middleware (guards()) e le sessioni (sessions()).
 * Application la crea con il Backend; senza Backend usa unavailableRoutes().
 */
final readonly class AccountModule implements RouteProvider
{
    /**
     * Ogni indirizzo dell'area clienti con i suoi metodi. La stessa tabella fa le rotte vere e quelle
     * "non ancora attiva", così le due versioni del sito hanno sempre gli stessi indirizzi.
     */
    public const array ENDPOINTS = [
        AccountUrls::HOME => ['GET'],
        AccountUrls::REGISTER => ['GET', 'POST'],
        AccountUrls::REGISTER_SENT => ['GET'],
        AccountUrls::VERIFY => ['GET', 'POST'],
        AccountUrls::VERIFY_RESEND => ['GET', 'POST'],
        AccountUrls::LOGIN => ['GET', 'POST'],
        AccountUrls::LOGOUT => ['GET', 'POST'],
        AccountUrls::FORGOT_PASSWORD => ['GET', 'POST'],
        AccountUrls::FORGOT_PASSWORD_SENT => ['GET'],
        AccountUrls::RESET_PASSWORD => ['GET', 'POST'],
    ];

    /**
     * @param list<Route> $routes
     */
    private function __construct(
        private array $routes,
        private AccountGuards $guards,
        private SessionManager $sessions,
    ) {}

    /**
     * @param Outbox $outbox le email che partono dopo la risposta (Application la svuota)
     * @param Controller $formExpired la pagina dei moduli respinti dalla protezione CSRF
     * @param Controller $notFound la pagina degli indirizzi che non esistono: la vede chi apre una pagina del titolare senza esserlo
     * @param list<DashboardSection> $dashboardSections le sezioni di altri moduli nella pagina dell'account (le prenotazioni, il pannello)
     * @param string|null $adminHome dove va il titolare dopo l'accesso, se non ha chiesto un'altra pagina
     */
    public static function create(
        Backend $backend,
        View $view,
        SiteConfig $site,
        Clock $clock,
        ExceptionReporter $reporter,
        Outbox $outbox,
        Controller $formExpired,
        Controller $notFound,
        array $dashboardSections = [],
        ?string $adminHome = null,
    ): self {
        $database = $backend->database;
        $users = new PdoUserRepository($database);
        $sessionRepository = new PdoSessionRepository($database);
        $tokenRepository = new PdoAccountTokenRepository($database);
        $rateLimits = new PdoRateLimitStore($database);
        $limiter = new RateLimiter($rateLimits, new Fingerprinter($backend->secret), $clock);
        $cookies = CookiePolicy::for($backend->origin);
        $dates = new ItalianDateFormatter();

        $sessions = new SessionManager(
            $sessionRepository,
            $users,
            $cookies,
            new Housekeeping($sessionRepository, $tokenRepository, $rateLimits, $clock),
            $clock,
            $backend->sessionIdleDays * 86_400,
        );
        $tokens = new AccountTokens($tokenRepository, $clock);
        $emails = new AccountEmails(new MailTemplates($view), $backend->origin, $site, $dates);
        $mail = new AccountMailer($backend->mailer, $outbox, $limiter, $reporter);
        $policy = PasswordPolicy::for($backend->passwords);

        $registration = new Registration($users, $tokens, $backend->passwords, $emails, $mail, $limiter, $clock);
        $verification = new EmailVerification($users, $tokenRepository, $tokens, $emails, $mail, $backend->passwords, $limiter, $clock);
        $login = new Login($users, $backend->passwords, $limiter, $clock);
        $reset = new PasswordReset($users, $tokenRepository, $tokens, $backend->passwords, $sessions, $emails, $mail, $limiter, $clock);

        $guards = new AccountGuards(
            new PrivatePage(),
            new CsrfProtection($cookies, $backend->origin, $formExpired),
            new StartSession($sessions),
            new RequireLogin(),
            new NoReferrer(),
            new RequireAdmin($notFound),
        );
        $pages = new AccountPages($view, $site, $clock);
        $signup = new SignupBrowser($cookies);

        $endpoints = [
            AccountUrls::HOME => [new AccountController($pages, $site, $dates, $dashboardSections), $guards->member()],
            AccountUrls::REGISTER => [new RegisterController($pages, $registration, $policy, $signup), $guards->page()],
            AccountUrls::REGISTER_SENT => [new CheckEmailController($pages, TokenPurpose::EmailVerification), $guards->page()],
            AccountUrls::VERIFY => [new VerifyEmailController($pages, $verification, $sessions, $signup), $guards->tokenPage()],
            AccountUrls::VERIFY_RESEND => [new ResendVerificationController($pages, $verification), $guards->page()],
            AccountUrls::LOGIN => [new LoginController($pages, $login, $sessions, new SignInDestination($adminHome)), $guards->page()],
            AccountUrls::LOGOUT => [new LogoutController($pages, $sessions), $guards->page()],
            AccountUrls::FORGOT_PASSWORD => [new ForgotPasswordController($pages, $reset), $guards->page()],
            AccountUrls::FORGOT_PASSWORD_SENT => [new CheckEmailController($pages, TokenPurpose::PasswordReset), $guards->page()],
            AccountUrls::RESET_PASSWORD => [new ResetPasswordController($pages, $reset, $policy, $sessions), $guards->tokenPage()],
        ];

        $routes = [];
        foreach (self::ENDPOINTS as $path => $methods) {
            [$controller, $middleware] = $endpoints[$path];
            $routes[] = Route::create($methods, $path, $controller)->through(...$middleware);
        }

        return new self($routes, $guards, $sessions);
    }

    /**
     * Le rotte dell'area clienti quando non è configurata: ogni indirizzo risponde con la stessa pagina (503).
     *
     * @return list<Route>
     */
    public static function unavailableRoutes(Controller $page): array
    {
        $routes = [];
        foreach (self::ENDPOINTS as $path => $methods) {
            $routes[] = Route::create($methods, $path, $page)->through(new PrivatePage());
        }

        return $routes;
    }

    public function routes(): array
    {
        return $this->routes;
    }

    /** I middleware per le pagine di altri moduli che devono sapere chi è il visitatore. */
    public function guards(): AccountGuards
    {
        return $this->guards;
    }

    /** Le sessioni, per chi deve aprirne o chiuderne una fuori dall'area clienti. */
    public function sessions(): SessionManager
    {
        return $this->sessions;
    }
}
