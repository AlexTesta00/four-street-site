<?php

declare(strict_types=1);

// Unico punto di ingresso PHP del sito (front controller): tutte le pagine passano da qui.

use FourStreet\Application;
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

Application::fromAppRoot($appRoot, __DIR__)
    ->handle(Request::fromGlobals($_SERVER))
    ->send();
