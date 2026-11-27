<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * L'avviso di una prenotazione disdetta dalla palestra: il bottone per un'altra fascia (se dal sito si prenota)
 * e il WhatsApp della palestra per chiedere. Senza allegati, e senza "se non sei stato tu".
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi,"
 * @var string $day "lunedì 7 dicembre 2026"
 * @var string $time "18:30"
 * @var string $endTime "19:30"
 * @var string|null $bookUrl la pagina delle fasce, se dal sito si prenota
 * @var string|null $whatsappUrl il WhatsApp della palestra, se c'è
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'Prenotazione disdetta',
    'preheader' => sprintf('La palestra ha disdetto la tua prenotazione delle %s di %s.', $time, $day),
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => sprintf('la palestra ha disdetto la tua prenotazione di %s, dalle %s alle %s. Ci dispiace per il cambio di programma.', $day, $time, $endTime), 'muted' => false]) ?>
<?php if ($bookUrl !== null): ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'Vuoi venire un altro giorno? Scegli la fascia.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/button', ['url' => $bookUrl, 'label' => 'Prenota una fascia']) ?>
<?php endif; ?>
<?php if ($whatsappUrl !== null): ?>
<?= $view->partial('emails/partials/paragraph', ['text' => new Html(sprintf(
    'Per qualsiasi domanda <a href="%s" style="color:#cdaa66;text-decoration:underline;">scrivici su WhatsApp</a>.',
    $view->e($whatsappUrl),
)), 'muted' => true]) ?>
<?php endif; ?>
