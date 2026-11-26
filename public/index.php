<?php

declare(strict_types=1);

// Unico punto di ingresso PHP del sito (front controller): tutte le pagine passano da qui.
// È anche l'unico punto in cui si leggono i superglobali e l'ambiente del processo.

use FourStreet\Application;
use FourStreet\Config\LocalConfig;
use FourStreet\Http\Request;

// Server di sviluppo (php -S … public/index.php): i file statici esistenti li serve lui direttamente.
// Mai i file nascosti, la cartella _app/ o altri script PHP: quelli passano dall'applicazione (404).
if (PHP_SAPI === 'cli-server') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestPath = rawurldecode(explode('?', is_string($requestUri) ? $requestUri : '/', 2)[0]);
    if (is_file(__DIR__ . $requestPath) && preg_match('#(?:^|/)(?:\.|_app(?:/|\z))|\.php\z#i', $requestPath) !== 1) {
        return false;
    }
}

// Nel pacchetto di deploy l'applicazione sta in _app/ accanto a questo file; in sviluppo è la radice della repo.
$appRoot = is_dir(__DIR__ . '/_app') ? __DIR__ . '/_app' : dirname(__DIR__);
require $appRoot . '/vendor/autoload.php';

// Un'altra configurazione locale al posto di config/local.php (la usa lo smoke test sul pacchetto).
$localConfig = getenv(LocalConfig::FILE_VARIABLE);

$application = Application::fromAppRoot($appRoot, __DIR__, is_string($localConfig) && $localConfig !== '' ? $localConfig : null);
$application->handle(Request::fromGlobals($_SERVER, $_POST, $_COOKIE))->send();

// La risposta è partita: si chiude la connessione (LiteSpeed su Hostinger, PHP-FPM), così le email
// in coda non fanno aspettare il browser. Il server di sviluppo non lo sa fare: lì si aspetta.
if (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} elseif (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
$application->finish();
