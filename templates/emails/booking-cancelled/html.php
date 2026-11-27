<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * L'avviso di una prenotazione disdetta dal cliente: il posto è tornato libero. Senza allegati.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi,"
 * @var string $day "lunedì 7 dicembre 2026"
 * @var string $time "18:30"
 * @var string $endTime "19:30"
 * @var string|null $bookUrl la pagina delle fasce, se dal sito si prenota
 * @var string $resetUrl la pagina della password dimenticata
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'Prenotazione disdetta',
    'preheader' => sprintf('Il posto delle %s di %s è tornato libero.', $time, $day),
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => sprintf('hai disdetto la prenotazione di %s, dalle %s alle %s: il posto è tornato libero.', $day, $time, $endTime), 'muted' => false]) ?>
<?php if ($bookUrl !== null): ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'Vuoi venire un altro giorno? Scegli la fascia.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/button', ['url' => $bookUrl, 'label' => 'Prenota una fascia']) ?>
<?php endif; ?>
<?= $view->partial('emails/partials/paragraph', ['text' => new Html(sprintf(
    'Se non sei stato tu, <a href="%s" style="color:#cdaa66;text-decoration:underline;">scegli subito una nuova password</a>.',
    $view->e($resetUrl),
)), 'muted' => true]) ?>
