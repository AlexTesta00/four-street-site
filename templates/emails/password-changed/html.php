<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Avviso dopo il cambio della password.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi," a email confermata, altrimenti "Ciao,"
 * @var string $when "giovedì 26 novembre 2026 alle 14:05", ora di San Marino
 * @var string $resetUrl la pagina della password dimenticata
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'La tua password è cambiata',
    'preheader' => 'Abbiamo chiuso l\'accesso al tuo account su tutti i dispositivi.',
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'la password del tuo account 4Street è stata cambiata ' . $when . '. Per sicurezza abbiamo chiuso l\'accesso su tutti i dispositivi.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => new Html(sprintf(
    'Se non sei stato tu, <a href="%s" style="color:#cdaa66;text-decoration:underline;">scegli subito una nuova password</a>.',
    $view->e($resetUrl),
)), 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'Se sei stato tu, non devi fare nulla.', 'muted' => true]) ?>
