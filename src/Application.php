<?php

declare(strict_types=1);

namespace FourStreet;

use FourStreet\Asset\AssetUrls;
use FourStreet\Asset\FilesystemMtimeLookup;
use FourStreet\Asset\MtimeLookup;
use FourStreet\Clock\Clock;
use FourStreet\Clock\SystemClock;
use FourStreet\Config\SiteConfig;
use FourStreet\Controller\CalendarController;
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
use FourStreet\Maps\MapLinks;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Icons;
use FourStreet\View\View;
use Throwable;

/**
 * Il sito: riceve una richiesta, restituisce una risposta.
 * create() è l'unico punto in cui le dipendenze vengono costruite e collegate.
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

    public function __construct(
        private Router $router,
        private Controller $notFound,
        private SecurityHeaders $securityHeaders,
        private ExceptionReporter $reporter,
    ) {}

    /**
     * Avvio in produzione e in sviluppo, a partire dalla radice dell'applicazione.
     *
     * @param string $appRoot cartella con config/, templates/ e vendor/
     * @param string $publicDirectory cartella pubblica (quella di index.php), per le versioni degli asset
     */
    public static function fromAppRoot(string $appRoot, string $publicDirectory): self
    {
        $site = SiteConfig::fromFile($appRoot . '/config/site.php');

        return self::create($site, $appRoot . '/templates', $publicDirectory, new SystemClock($site->timezone));
    }

    public static function create(
        SiteConfig $site,
        string $templateDirectory,
        string $publicDirectory,
        Clock $clock,
        MtimeLookup $mtimes = new FilesystemMtimeLookup(),
        ExceptionReporter $reporter = new ErrorLogReporter(),
    ): self {
        $view = new View($templateDirectory, new AssetUrls($publicDirectory, $mtimes), new Icons());
        $dates = new ItalianDateFormatter();
        $maps = new MapLinks($site->address, $site->mapsUrl);

        $router = new Router([
            Route::get('/', new HomeController($view, $site, $clock, $dates, $maps)),
            Route::get('/links/', new LinksController($view, $site, $clock, $dates, $maps)),
            // Indirizzo già condiviso: non cambiarlo.
            Route::get('/calendario.php', new CalendarController($site, $clock)),
            // Vecchi indirizzi dei file PHP, prima del front controller.
            Route::get('/index.php', new RedirectController('/')),
            Route::get('/links/index.php', new RedirectController('/links/')),
        ]);

        return new self($router, new NotFoundController($view, $site), SecurityHeaders::forSite(), $reporter);
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
