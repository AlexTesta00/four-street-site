<?php

declare(strict_types=1);

use FourStreet\Booking\BookingStatus;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * Una fascia dove il titolare non aggiunge nessuno, con il motivo e un solo bottone, verso il giorno.
 * 404 se la fascia (o il giorno chiesto) non c'è; 409 se è già iniziata o è piena.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var BookingStatus $status
 * @var string|null $slotDay "lunedì 7 dicembre"
 * @var string|null $slotTime "18:30"
 * @var int $capacity
 * @var string $dayUrl il giorno della fascia nel pannello (senza fascia, il giorno di oggi)
 * @var Alert|null $alert troppi tentativi
 */

// Per piena e iniziata la fascia è nota: giorno e orario ci sono.
$day = (string) $slotDay;
$time = (string) $slotTime;
[$heading, $lead, $button] = match ($status) {
    BookingStatus::Full => [
        'Fascia al completo.',
        sprintf('I %d posti della fascia delle %s di %s sono tutti presi: per aggiungere qualcuno, prima disdici una prenotazione.', $capacity, $time, $day),
        'Torna al giorno',
    ],
    BookingStatus::Closed => [
        'La fascia è iniziata.',
        sprintf('La fascia delle %s di %s è già iniziata: non si aggiunge più nessuno.', $time, $day),
        'Torna al giorno',
    ],
    default => [
        'Fascia non trovata.',
        'Qui non c\'è nessuna fascia: forse il giorno è chiuso, è prima dell\'inaugurazione, oppure l\'indirizzo è scritto male.',
        'Torna alla gestione',
    ],
};

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Gestione · ' . rtrim($heading, '.'),
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => $heading,
    'lead' => $lead,
    'compact' => true,
    // Una pagina della sezione del giorno (il bottone porta lì), non la pagina del link "Oggi".
    'nav' => $view->partial('partials/admin/nav', ['current' => 'day', 'exact' => false]),
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<a href="<?= $view->e($dayUrl) ?>" class="fs-btn fs-btn--primary fs-btn--block"><?= $view->e($button) ?></a>
