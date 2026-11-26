<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Un link di conferma già usato, su un account confermato (per esempio riaperto dall'email, o da un altro
 * dispositivo dopo la conferma). Nessun errore: l'account è attivo, basta accedere.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Email confermata',
    'eyebrow' => 'Registrazione',
    'heading' => 'Email già confermata.',
    'lead' => 'Il tuo account è attivo: accedi con email e password.',
]);
?>
<a href="<?= $view->e(AccountUrls::LOGIN) ?>" class="fs-btn fs-btn--primary fs-btn--block">Accedi</a>
