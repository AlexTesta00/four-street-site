<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * La conferma di una prenotazione: i dettagli, il bottone verso l'account (dove si disdice) e, in allegato,
 * l'evento da aggiungere al calendario.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi,"
 * @var string $day "lunedì 7 dicembre 2026"
 * @var string $time "18:30"
 * @var string $endTime "19:30"
 * @var int $seat
 * @var int $capacity
 * @var string $accountUrl la pagina dell'account, dove si disdice
 * @var string $cancellation fino a quando si disdice: "fino all'inizio della fascia"
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'Prenotazione confermata',
    'preheader' => sprintf('%s, ore %s: il tuo posto è prenotato.', ucfirst($day), $time),
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'il tuo posto è prenotato. Ti aspettiamo.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/details', ['rows' => [
    'Giorno' => ucfirst($day),
    'Orario' => $time . ' – ' . $endTime,
    'Posto' => sprintf('%d di %d', $seat, $capacity),
    'Dove' => $address,
]]) ?>
<?= $view->partial('emails/partials/button', ['url' => $accountUrl, 'label' => 'Le tue prenotazioni']) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => sprintf('In allegato c\'è l\'evento da aggiungere al calendario. Se non puoi venire, disdici dal tuo account %s: il posto torna libero per un altro.', $cancellation), 'muted' => true]) ?>
