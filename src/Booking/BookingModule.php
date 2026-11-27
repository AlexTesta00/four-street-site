<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\AccountGuards;
use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Http\DashboardSection;
use FourStreet\Backend;
use FourStreet\Booking\Http\BlockedBookingPage;
use FourStreet\Booking\Http\BookingsComingSoonController;
use FourStreet\Booking\Http\BookingsOpen;
use FourStreet\Booking\Http\BookingsSection;
use FourStreet\Booking\Http\CancelBookingController;
use FourStreet\Booking\Http\ConfirmBookingController;
use FourStreet\Booking\Http\DayController;
use FourStreet\Booking\Landing\ExamplePreviewSource;
use FourStreet\Booking\Landing\FailSafePreviewSource;
use FourStreet\Booking\Landing\LivePreviewSource;
use FourStreet\Booking\Landing\SlotPreviewSource;
use FourStreet\Booking\Landing\TimetablePreviewSource;
use FourStreet\Booking\Schedule\PdoSchedule;
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
 * della landing (landingSlots()). L'orario dal database (schedule()) lo crea Application una volta sola e lo dà
 * a tutti, con il calendario che lo legge: ogni pagina vede lo stesso orario, letto una volta per richiesta.
 *
 * Con booking.online a false le pagine per prenotare rispondono "Prenotazioni presto"; lo stesso se il titolare
 * le spegne dal pannello (BookingsOpen). La disdetta e l'elenco nell'account restano, per le prenotazioni già fatte.
 *
 * I servizi di dominio (SlotBooking, BookingCancellation, SlotBoard, BookingRepository e, per il titolare,
 * Staff\StaffBooking, Staff\StaffCancellation, Staff\DayRoster, Schedule\ScheduleEditor) non sanno nulla di HTTP:
 * il pannello del titolare (FourStreet\Admin) li compone con le sue pagine.
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
     * L'orario e le regole del database, con quelli di config/site.php come valori iniziali. Uno per Application
     * (cioè per richiesta), condiviso da tutti i moduli: legge una volta e ricorda.
     */
    public static function schedule(Backend $backend, SiteConfig $site): PdoSchedule
    {
        return new PdoSchedule($backend->database, $site->booking);
    }

    /**
     * @param AccountGuards $guards i middleware dell'area clienti: visitatore riconosciuto, accesso obbligatorio
     * @param BookingCalendar $calendar il calendario sull'orario del database (schedule())
     */
    public static function create(Backend $backend, View $view, SiteConfig $site, Clock $clock, ExceptionReporter $reporter, AccountGuards $guards, BookingCalendar $calendar): self
    {
        $bookings = new PdoBookingRepository($backend->database);
        $limiter = new RateLimiter(new PdoRateLimitStore($backend->database), new Fingerprinter($backend->secret), $clock);
        $dates = new ItalianDateFormatter();
        $notifier = new BookingNotifier(new BookingEmails(new MailTemplates($view), $backend->origin, $site, $dates, $calendar), $backend->mailer, $reporter);
        $pages = new AccountPages($view, $site, $clock, 'pages/booking');
        $blocked = new BlockedBookingPage($pages, $calendar, $dates, $clock);
        $comingSoon = new BookingsComingSoonController($pages);

        $cancel = [new CancelBookingController($pages, new BookingCancellation($calendar, $bookings, $notifier, $limiter, $clock), $calendar, $dates), $guards->member()];
        if ($site->booking->online) {
            // Accese nel file: le spegne ancora il titolare dal pannello, e allora "presto" prima di ogni altra cosa.
            $open = new BookingsOpen($calendar, $comingSoon);
            $day = [new DayController($pages, new SlotBoard($calendar, $bookings, $clock), $blocked, $dates), [$open, ...$guards->page()]];
            $confirm = [new ConfirmBookingController($pages, new SlotBooking($calendar, $bookings, $notifier, $limiter, $clock), $blocked, $calendar, $dates), [$open, ...$guards->member()]];
        } else {
            $closed = [$comingSoon, [new PrivatePage()]];
            [$day, $confirm] = [$closed, $closed];
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

    /**
     * "Le tue prenotazioni", per la pagina dell'account (AccountModule::create()).
     *
     * @param BookingCalendar $calendar il calendario sull'orario del database (schedule())
     */
    public static function dashboardSection(Backend $backend, View $view, SiteConfig $site, Clock $clock, ExceptionReporter $reporter, BookingCalendar $calendar): DashboardSection
    {
        $agenda = new BookingAgenda($calendar, new PdoBookingRepository($backend->database), $clock);

        return new BookingsSection($view, $agenda, $calendar, $site->booking->capacity, new ItalianDateFormatter(), $reporter);
    }

    /**
     * Le fasce della landing. Con le prenotazioni attive (e il Backend) quelle vere, dentro il guscio che
     * a ogni errore registra e ripiega sugli orari senza numeri, e poi sugli esempi; altrimenti gli esempi.
     * Gli orari senza numeri sono quelli di config/site.php: se il database non risponde, il file è la riserva.
     *
     * @param BookingCalendar|null $calendar il calendario sull'orario del database (schedule()), se c'è il Backend
     */
    public static function landingSlots(?Backend $backend, SiteConfig $site, Clock $clock, ExceptionReporter $reporter, ?BookingCalendar $calendar = null): SlotPreviewSource
    {
        $examples = new ExamplePreviewSource($site->booking->examples);
        if ($backend === null || !$site->booking->online) {
            return $examples;
        }

        $dates = new ItalianDateFormatter();
        $live = $calendar ?? BookingCalendar::from(self::schedule($backend, $site), $site);

        return new FailSafePreviewSource(
            new LivePreviewSource(new SlotBoard($live, new PdoBookingRepository($backend->database), $clock), $live, $site->booking->examples, $dates),
            new TimetablePreviewSource(BookingCalendar::forSite($site), $dates, $clock),
            $examples,
            $reporter,
        );
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
