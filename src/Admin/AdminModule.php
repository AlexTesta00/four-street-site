<?php

declare(strict_types=1);

namespace FourStreet\Admin;

use FourStreet\Account\AccountGuards;
use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Http\DashboardSection;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Admin\Http\AdminLinkSection;
use FourStreet\Admin\Http\CancelBookingController;
use FourStreet\Admin\Http\NewBookingController;
use FourStreet\Admin\Http\RosterController;
use FourStreet\Admin\Http\StaffBlockedPage;
use FourStreet\Backend;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\Staff\DayRoster;
use FourStreet\Booking\Staff\StaffBooking;
use FourStreet\Booking\Staff\StaffCancellation;
use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Error\ExceptionReporter;
use FourStreet\Http\Controller;
use FourStreet\Http\Middleware\PrivatePage;
use FourStreet\Http\Routing\Route;
use FourStreet\Http\Routing\RouteProvider;
use FourStreet\Mail\MailTemplates;
use FourStreet\Security\Fingerprinter;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\View;

/**
 * Il pannello del titolare: chi è prenotato e in che fascia, e da lì una prenotazione nuova o una disdetta.
 * Niente di più: orari, regole e ruoli restano in config/site.php e nella console.
 *
 * Il modulo porta solo HTTP (controller, modulo, avvisi, rotte): le regole stanno nei servizi di dominio delle
 * prenotazioni (Booking\Staff), senza HTTP. Ogni rotta passa da AccountGuards::admin(): niente cache, CSRF,
 * accesso obbligatorio e, per chi non è titolare, la pagina 404 di un indirizzo che non esiste.
 * Application ne prende le rotte (create(), oppure unavailableRoutes() senza Backend) e il link al pannello
 * nella pagina dell'account (dashboardSection()).
 */
final readonly class AdminModule implements RouteProvider
{
    /** Ogni indirizzo con i suoi metodi: la stessa tabella fa le rotte vere e quelle "non ancora attiva". */
    public const array ENDPOINTS = [
        AdminUrls::HOME => ['GET'],
        AdminUrls::NEW_BOOKING => ['GET', 'POST'],
        AdminUrls::CANCEL => ['GET', 'POST'],
    ];

    /**
     * @param list<Route> $routes
     */
    private function __construct(
        private array $routes,
    ) {}

    /**
     * @param AccountGuards $guards i middleware dell'area clienti: con admin() solo il titolare entra
     */
    public static function create(Backend $backend, View $view, SiteConfig $site, Clock $clock, ExceptionReporter $reporter, AccountGuards $guards): self
    {
        $calendar = BookingCalendar::forSite($site);
        $bookings = new PdoBookingRepository($backend->database);
        $users = new PdoUserRepository($backend->database);
        $limiter = new RateLimiter(new PdoRateLimitStore($backend->database), new Fingerprinter($backend->secret), $clock);
        $dates = new ItalianDateFormatter();
        $notifier = new BookingNotifier(new BookingEmails(new MailTemplates($view), $backend->origin, $site, $dates, $calendar), $backend->mailer, $reporter);
        $pages = new AccountPages($view, $site, $clock, 'pages/admin');
        $blocked = new StaffBlockedPage($pages, $dates, $site->booking->capacity);

        $controllers = [
            AdminUrls::HOME => new RosterController($pages, new DayRoster($calendar, $bookings, $clock), $blocked, $dates),
            AdminUrls::NEW_BOOKING => new NewBookingController($pages, new StaffBooking($calendar, $bookings, $users, $notifier, $limiter, $clock), $blocked, $dates, $clock),
            AdminUrls::CANCEL => new CancelBookingController($pages, new StaffCancellation($bookings, $users, $notifier, $limiter, $clock), $calendar, $dates),
        ];

        $routes = [];
        foreach (self::ENDPOINTS as $path => $methods) {
            $routes[] = Route::create($methods, $path, $controllers[$path])->through(...$guards->admin());
        }

        return new self($routes);
    }

    /**
     * Le rotte senza Backend (nessun config/local.php): rispondono come il resto dell'area clienti non configurata
     * (503, una pagina cortese), mai con un errore.
     *
     * @param Controller $accountUnavailable la pagina dell'area clienti non configurata
     *
     * @return list<Route>
     */
    public static function unavailableRoutes(Controller $accountUnavailable): array
    {
        $routes = [];
        foreach (self::ENDPOINTS as $path => $methods) {
            $routes[] = Route::create($methods, $path, $accountUnavailable)->through(new PrivatePage());
        }

        return $routes;
    }

    /** Il link al pannello nella pagina dell'account, solo per il titolare (AccountModule::create()). */
    public static function dashboardSection(View $view): DashboardSection
    {
        return new AdminLinkSection($view);
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
