<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Una disdetta del pannello che non si può fare (404): la prenotazione non c'è, è già disdetta o la sua fascia
 * è già iniziata.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Gestione · Niente da disdire',
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => 'Niente da disdire.',
    'lead' => 'A questo indirizzo non c\'è una prenotazione da disdire: forse è già stata disdetta, oppure la sua fascia è già iniziata.',
    'compact' => true,
]);
?>
<a href="<?= $view->e(AdminUrls::HOME) ?>" class="fs-btn fs-btn--primary fs-btn--block">Torna alla gestione</a>
