<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Il link per scegliere una nuova password.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi," a email confermata, altrimenti "Ciao,"
 * @var string $url il link per la nuova password
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'Scegli una nuova password',
    'preheader' => 'Il link per la nuova password del tuo account 4Street. Vale un\'ora.',
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'hai chiesto di cambiare la password del tuo account 4Street. Tocca il bottone e scegli quella nuova.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/button', ['url' => $url, 'label' => 'Scegli la password']) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'Il link vale un\'ora e si usa una volta sola. Se non l\'hai chiesto tu, ignora questa email: la password resta quella di prima.', 'muted' => true]) ?>
