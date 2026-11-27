<?php

declare(strict_types=1);

use FourStreet\Booking\RosterEntry;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/prenotazioni/{id}/disdici/ con GET: la conferma della disdetta, con chi è, quando e dove. Si disdice
 * solo con il bottone (POST e token CSRF): un link non disdice mai nulla. La nota dice se alla persona arriva
 * un'email (cliente registrato) o se la deve avvisare il titolare (senza account).
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var RosterEntry $entry
 * @var string $day "Lunedì 7 dicembre 2026"
 * @var string $endTime "19:30"
 * @var int $capacity
 * @var string $action dove va il modulo
 * @var string $backUrl il giorno, nel pannello
 * @var Alert|null $alert troppi tentativi
 */

$booking = $entry->booking;
$start = $booking->time->value;

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Gestione · Disdici la prenotazione',
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => 'Disdici la prenotazione?',
    'lead' => 'Il posto torna libero per un altro.',
    'compact' => true,
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<dl class="fs-dl fs-dl--stack">
  <div class="fs-dl__row">
    <dt class="fs-label">Chi</dt>
    <dd><?= $view->e($entry->name) ?><br><span class="text-body-sm text-fg-muted"><?= $view->e($entry->email === null ? 'Senza account' : $entry->email->value) ?></span></dd>
  </div>
  <div class="fs-dl__row">
    <dt class="fs-label">Giorno</dt>
    <dd><?= $view->e($day) ?></dd>
  </div>
  <div class="fs-dl__row">
    <dt class="fs-label">Orario</dt>
    <dd><span class="sr-only"><?= $view->e('Dalle ' . $start . ' alle ' . $endTime) ?></span><span aria-hidden="true"><?= $view->e($start) ?> – <?= $view->e($endTime) ?></span></dd>
  </div>
  <div class="fs-dl__row">
    <dt class="fs-label">Posto</dt>
    <dd><?= $view->e($booking->seat()) ?> di <?= $view->e($capacity) ?></dd>
  </div>
</dl>
<form method="post" action="<?= $view->e($action) ?>" class="fs-actions" data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Disdici la prenotazione</button>
  <a href="<?= $view->e($backUrl) ?>" class="fs-btn fs-btn--secondary fs-btn--block">Tienila</a>
</form>
<div class="fs-auth__note">
  <?= $view->icon($entry->email === null ? 'info' : 'mail', 'fs-auth__note-icon') ?>
  <p><?= $view->e($entry->email === null
      ? 'Non ha l\'account: nessuna email. Avvisalo tu, per esempio su WhatsApp.'
      : 'Gli mandiamo un\'email per avvisarlo che la prenotazione l\'ha disdetta la palestra.') ?></p>
</div>
