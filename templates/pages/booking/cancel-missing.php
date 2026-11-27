<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Una disdetta che non si può fare (404): la prenotazione non c'è, non è tua, è già disdetta o la fascia
 * è già iniziata. Per chi la chiede è sempre la stessa pagina, che non dice di chi sia la prenotazione.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Prenotazione non trovata',
    'eyebrow' => 'Il tuo account',
    'heading' => 'Prenotazione non trovata.',
    'lead' => 'Non c\'è nessuna prenotazione da disdire a questo indirizzo: forse l\'hai già disdetta, oppure è troppo tardi perché la fascia sta per iniziare o è iniziata.',
    'compact' => true,
]);
?>
<a href="<?= $view->e(AccountUrls::HOME) ?>" class="fs-btn fs-btn--primary fs-btn--block">Le tue prenotazioni</a>
