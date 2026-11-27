<?php

declare(strict_types=1);

namespace FourStreet;

use FourStreet\Account\AccountModule;
use FourStreet\Account\AccountUrls;
use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Http\AccountUnavailableController;
use FourStreet\Admin\AdminModule;
use FourStreet\Admin\AdminUrls;
use FourStreet\Asset\AssetUrls;
use FourStreet\Asset\FilesystemMtimeLookup;
use FourStreet\Asset\MtimeLookup;
use FourStreet\Booking\BookingModule;
use FourStreet\Booking\BookingUrls;
use FourStreet\Clock\Clock;
use FourStreet\Clock\SystemClock;
use FourStreet\Config\LocalConfig;
use FourStreet\Config\SiteConfig;
use FourStreet\Controller\CalendarController;
use FourStreet\Controller\FormExpiredController;
use FourStreet\Controller\HomeController;
use FourStreet\Controller\LinksController;
use FourStreet\Controller\NotFoundController;
use FourStreet\Controller\RedirectController;
use FourStreet\Error\ErrorLogReporter;
use FourStreet\Error\ExceptionReporter;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Http\Routing\CanonicalRedirect;
use FourStreet\Http\Routing\MethodNotAllowed;
use FourStreet\Http\Routing\Route;
use FourStreet\Http\Routing\RouteFound;
use FourStreet\Http\Routing\Router;
use FourStreet\Http\SecurityHeaders;
use FourStreet\Mail\Outbox;
use FourStreet\Maps\MapLinks;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Icons;
use FourStreet\View\View;
use Throwable;

/**
 * Il sito: riceve una richiesta, restituisce una risposta; poi, con finish(), fa il lavoro che può
 * aspettare (le email in coda). Un'Application serve una richiesta sola, come in index.php.
 * create() è l'unico punto in cui le dipendenze vengono costruite e collegate; ogni modulo
 * (l'area clienti, le prenotazioni, il pannello del titolare) porta le sue rotte già pronte.
 */
final readonly class Application
{
    private const string ERROR_PAGE = <<<'HTML'
        <!doctype html>
        <html lang="it">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>4Street</title>
        <p>Si è verificato un errore. Riprova tra poco.</p>
        </html>
        HTML;

    /**
     * @param Outbox|null $outbox le email da mandare dopo la risposta; null senza Backend
     */
    public function __construct(
        private Router $router,
        private Controller $notFound,
        private SecurityHeaders $securityHeaders,
        private ExceptionReporter $reporter,
        private ?Outbox $outbox = null,
    ) {}

    /**
     * Avvio in produzione e in sviluppo, a partire dalla radice dell'applicazione.
     *
     * @param string $appRoot cartella con config/, templates/ e vendor/
     * @param string $publicDirectory cartella pubblica (quella di index.php), per le versioni degli asset
     * @param string|null $localConfigFile la configurazione locale indicata dalla variabile d'ambiente, se c'è
     */
    public static function fromAppRoot(string $appRoot, string $publicDirectory, ?string $localConfigFile = null): self
    {
        $site = SiteConfig::fromFile($appRoot . '/config/site.php');
        $clock = new SystemClock($site->timezone);
        $local = LocalConfig::load($localConfigFile, $appRoot, $site->siteUrl);

        return self::create(
            $site,
            $appRoot . '/templates',
            $publicDirectory,
            $clock,
            backend: $local === null ? null : Backend::fromLocalConfig($local, $clock),
        );
    }

    /**
     * @param Backend|null $backend database, posta e impostazioni del server; null se l'area clienti non è configurata
     */
    public static function create(
        SiteConfig $site,
        string $templateDirectory,
        string $publicDirectory,
        Clock $clock,
        MtimeLookup $mtimes = new FilesystemMtimeLookup(),
        ExceptionReporter $reporter = new ErrorLogReporter(),
        ?Backend $backend = null,
    ): self {
        $view = new View($templateDirectory, new AssetUrls($publicDirectory, $mtimes), new Icons());
        $dates = new ItalianDateFormatter();
        $maps = new MapLinks($site->address, $site->mapsUrl);
        $notFound = new NotFoundController($view, $site);

        // Senza database e posta l'area clienti e il pannello del titolare rispondono "non ancora attiva" e le
        // prenotazioni "presto"; home, link e calendario funzionano come sempre.
        if ($backend === null) {
            $outbox = null;
            $accountUnavailable = new AccountUnavailableController(new AccountPages($view, $site, $clock));
            $accountRoutes = AccountModule::unavailableRoutes($accountUnavailable);
            $bookingRoutes = BookingModule::unavailableRoutes(BookingModule::comingSoon($view, $site, $clock), $accountUnavailable);
            $adminRoutes = AdminModule::unavailableRoutes($accountUnavailable);
        } else {
            $outbox = new Outbox($backend->mailer, $reporter);
            $account = AccountModule::create(
                $backend,
                $view,
                $site,
                $clock,
                $reporter,
                $outbox,
                new FormExpiredController($view, $site, $clock),
                // Chi apre il pannello senza essere titolare vede la stessa pagina di un indirizzo che non esiste.
                $notFound,
                [AdminModule::dashboardSection($view), BookingModule::dashboardSection($backend, $view, $site, $clock, $reporter)],
                AdminUrls::HOME,
            );
            $accountRoutes = $account->routes();
            $bookingRoutes = BookingModule::create($backend, $view, $site, $clock, $reporter, $account->guards())->routes();
            $adminRoutes = AdminModule::create($backend, $view, $site, $clock, $reporter, $account->guards())->routes();
        }
        $bookingsOnline = $backend !== null && $site->booking->online;

        $router = new Router([
            // Con le prenotazioni attive le fasce vere (una lettura, dentro un guscio); altrimenti quelle di esempio.
            Route::get('/', new HomeController(
                $view,
                $site,
                $clock,
                $dates,
                $maps,
                BookingModule::landingSlots($backend, $site, $clock, $reporter),
                $backend === null ? null : AccountUrls::HOME,
            )),
            Route::get('/links/', new LinksController($view, $site, $clock, $dates, $maps, $bookingsOnline ? BookingUrls::DAY : null)),
            // Indirizzo già condiviso: non cambiarlo.
            Route::get('/calendario.php', new CalendarController($site, $clock)),
            // Vecchi indirizzi dei file PHP, prima del front controller.
            Route::get('/index.php', new RedirectController('/')),
            Route::get('/links/index.php', new RedirectController('/links/')),
            ...$accountRoutes,
            ...$bookingRoutes,
            ...$adminRoutes,
        ]);

        // HTTPS lo dice l'origine pubblica, come per i cookie: base_url, oppure site_url senza config/local.php.
        $https = $backend !== null ? $backend->origin->isSecure() : str_starts_with(strtolower((string) $site->siteUrl), 'https://');

        return new self($router, $notFound, SecurityHeaders::forSite($https), $reporter, $outbox);
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (Throwable $error) {
            $this->reporter->report($error);
            $response = Response::html(self::ERROR_PAGE, 500);
        }

        $response = $this->securityHeaders->applyTo($response);

        return $request->method === 'HEAD' ? $response->withoutBody() : $response;
    }

    /**
     * Il lavoro che può aspettare la risposta: le email in coda (Outbox). index.php lo chiama quando la
     * risposta è già partita; un errore qui non ha più una pagina a cui arrivare, quindi si registra soltanto.
     */
    public function finish(): void
    {
        try {
            $this->outbox?->flush();
        } catch (Throwable $error) {
            $this->reporter->report($error);
        }
    }

    private function dispatch(Request $request): Response
    {
        $result = $this->router->match($request->method, $request->path);

        return match (true) {
            $result instanceof RouteFound => ($result->controller)($request->withAttributes($result->parameters)),
            $result instanceof CanonicalRedirect => (new RedirectController($result->location, $result->status))($request),
            $result instanceof MethodNotAllowed => Response::text('Metodo non consentito.', 405)
                ->withHeader('Allow', implode(', ', $result->allowedMethods)),
            default => ($this->notFound)($request),
        };
    }
}
