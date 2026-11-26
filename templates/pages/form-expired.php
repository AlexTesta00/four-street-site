<?php

declare(strict_types=1);

use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Un modulo respinto dalla protezione CSRF (403): di solito una pagina rimasta aperta a lungo,
 * oppure i cookie bloccati. Nessun dato è stato inviato: si ricarica e si riprova.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $retryUrl la pagina del modulo, da ricaricare
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Modulo scaduto',
    'eyebrow' => 'Errore 403',
    'heading' => 'Il modulo è scaduto.',
    'lead' => 'Per sicurezza non abbiamo inviato i dati. Succede quando la pagina resta aperta a lungo o quando i cookie sono bloccati: ricaricala e riprova.',
]);
?>
<a href="<?= $view->e($retryUrl) ?>" class="fs-btn fs-btn--primary fs-btn--block">Ricarica e riprova</a>
