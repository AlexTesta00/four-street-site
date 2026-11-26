<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Qualcuno ha provato a registrarsi con un'email che ha già un account confermato.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi," a email confermata, altrimenti "Ciao,"
 * @var string $loginUrl
 * @var string $resetUrl
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'Hai già un account',
    'preheader' => 'Qualcuno ha provato a registrarsi con questo indirizzo: l\'account c\'è già.',
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'qualcuno, forse tu, ha provato a creare un account 4Street con questo indirizzo. L\'account c\'è già: accedi con la tua password.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/button', ['url' => $loginUrl, 'label' => 'Accedi']) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => new Html(sprintf(
    'Non ricordi la password? <a href="%s" style="color:#cdaa66;text-decoration:underline;">Scegline una nuova</a>.',
    $view->e($resetUrl),
)), 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'Se non sei stato tu, ignora questa email: il tuo account non è cambiato.', 'muted' => true]) ?>
