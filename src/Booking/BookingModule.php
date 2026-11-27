<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\AccountGuards;
use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Http\DashboardSection;
use FourStreet\Backend;
use FourStreet\Booking\Http\BlockedBookingPage;
use FourStreet\Booking\Http\BookingsComingSoonController;
use FourStreet\Booking\Http\BookingsSection;
use FourStreet\Booking\Http\CancelBookingController;
use FourStreet\Booking\Http\ConfirmBookingController;
use FourStreet\Booking\Http\DayController;
use FourStreet\Booking\Landing\ExamplePreviewSource;
use FourStreet\Booking\Landing\FailSafePreviewSource;
use FourStreet\Booking\Landing\LivePreviewSource;
use FourStreet\Booking\Landing\SlotPreviewSource;
use FourStreet\Booking\Landing\TimetablePreviewSource;
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
 * Le prenotazioni delle fasce: costruisce e collega calendario, servizi, controller e rotte del modulo.
 * Application ne prende quattro cose: le rotte (create(), oppure unavailableRoutes() senza Backend),
 * la sezione della pagina dell'account (dashboardSection(), da passare all'area clienti) e le fasce
 * della landing (landingSlots()).
 *
 * Con booking.online a false le pagine per prenotare rispondono "Prenotazioni presto"; la disdetta
 * e l'elenco nell'account restano, per le prenotazioni già fatte.
 *
 * I servizi di dominio (SlotBooking, BookingCancellation, SlotBoard, BookingRepository e, per il titolare,
 * Staff\StaffBooking, Staff\StaffCancellation, Staff\DayRoster) non sanno nulla di HTTP: il pannello del titolare
 * (FourStreet\Admin) li compone con le sue pagine.
 */
final readonly class BookingModule implements RouteProvider
{
    /** Ogni indirizzo con i suoi metodi: la stessa tabella fa le rotte vere e quelle "presto". */
    public const array ENDPOINTS = [
        BookingUrls::DAY => ['GET'],
        BookingUrls::CONFIRM => ['GET', 'POST'],
        BookingUrls::CANCEL => ['GET', 'POST'],
    ];

    /**
     * @param list<Route> $routes
     */
    private function __construct(
        private array $routes,
    ) {}

    /**
     * @param AccountGuards $guards i middleware dell'area clienti: visitatore riconosciuto, accesso obbligatorio
     */
    public static function create(Backend $backend, View $view, SiteConfig $site, Clock $clock, ExceptionReporter $reporter, AccountGuards $guards): self
    {
        $calendar = BookingCalendar::forSite($site);
        $bookings = new PdoBookingRepository($backend->database);
        $limiter = new RateLimiter(new PdoRateLimitStore($backend->database), new Fingerprinter($backend->secret), $clock);
        $dates = new ItalianDateFormatter();
        $notifier = new BookingNotifier(new BookingEmails(new MailTemplates($view), $backend->origin, $site, $dates, $calendar), $backend->mailer, $reporter);
        $pages = new AccountPages($view, $site, $clock, 'pages/booking');
        $blocked = new BlockedBookingPage($pages, $calendar, $dates, $clock);

        $cancel = [new CancelBookingController($pages, new BookingCancellation($calendar, $bookings, $notifier, $limiter, $clock), $calendar, $dates), $guards->member()];
        if ($site->booking->online) {
            $day = [new DayController($pages, new SlotBoard($calendar, $bookings, $clock), $blocked, $dates), $guards->page()];
            $confirm = [new ConfirmBookingController($pages, new SlotBooking($calendar, $bookings, $notifier, $limiter, $clock), $blocked, $calendar, $dates), $guards->member()];
        } else {
            $comingSoon = [new BookingsComingSoonController($pages), [new PrivatePage()]];
            [$day, $confirm] = [$comingSoon, $comingSoon];
        }

        $endpoints = [BookingUrls::DAY => $day, BookingUrls::CONFIRM => $confirm, BookingUrls::CANCEL => $cancel];
        $routes = [];
        foreach (self::ENDPOINTS as $path => $methods) {
            [$controller, $middleware] = $endpoints[$path];
            $routes[] = Route::create($methods, $path, $controller)->through(...$middleware);
        }

        return new self($routes);
    }

    /**
     * Le rotte senza Backend (nessun config/local.php): le pagine per prenotare rispondono "Prenotazioni presto",
     * la disdetta come il resto dell'area clienti ("non ancora attiva"). Sempre 503, mai un errore.
     *
     * @param Controller $comingSoon la pagina "Prenotazioni presto"
     * @param Controller $accountUnavailable la pagina dell'area clienti non configurata
     *
     * @return list<Route>
     */
    public static function unavailableRoutes(Controller $comingSoon, Controller $accountUnavailable): array
    {
        $routes = [];
        foreach (self::ENDPOINTS as $path => $methods) {
            $routes[] = Route::create($methods, $path, $path === BookingUrls::CANCEL ? $accountUnavailable : $comingSoon)->through(new PrivatePage());
        }

        return $routes;
    }

    /** La pagina "Prenotazioni presto", per unavailableRoutes(). */
    public static function comingSoon(View $view, SiteConfig $site, Clock $clock): Controller
    {
        return new BookingsComingSoonController(new AccountPages($view, $site, $clock, 'pages/booking'));
    }

    /** "Le tue prenotazioni", per la pagina dell'account (AccountModule::create()). */
    public static function dashboardSection(Backend $backend, View $view, SiteConfig $site, Clock $clock, ExceptionReporter $reporter): DashboardSection
    {
        $agenda = new BookingAgenda(BookingCalendar::forSite($site), new PdoBookingRepository($backend->database), $clock);

        return new BookingsSection($view, $agenda, $site->booking, new ItalianDateFormatter(), $reporter);
    }

    /**
     * Le fasce della landing. Con le prenotazioni attive (e il Backend) quelle vere, dentro il guscio che
     * a ogni errore registra e ripiega sugli orari senza numeri, e poi sugli esempi; altrimenti gli esempi.
     */
    public static function landingSlots(?Backend $backend, SiteConfig $site, Clock $clock, ExceptionReporter $reporter): SlotPreviewSource
    {
        $examples = new ExamplePreviewSource($site->booking->examples);
        if ($backend === null || !$site->booking->online) {
            return $examples;
        }

        $calendar = BookingCalendar::forSite($site);
        $dates = new ItalianDateFormatter();

        return new FailSafePreviewSource(
            new LivePreviewSource(new SlotBoard($calendar, new PdoBookingRepository($backend->database), $clock), $dates),
            new TimetablePreviewSource($calendar, $dates, $clock),
            $examples,
            $reporter,
        );
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
