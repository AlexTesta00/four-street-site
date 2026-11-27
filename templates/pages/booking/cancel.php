<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Booking\Booking;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /account/prenotazioni/{id}/disdici/ con GET: la conferma della disdetta. Si disdice solo con il bottone
 * (POST e token CSRF): un link non disdice mai nulla.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var Booking $booking
 * @var string $day "Lunedì 7 dicembre 2026"
 * @var string $endTime "19:30"
 * @var int $capacity
 * @var string $action dove va il modulo
 * @var Alert|null $alert troppi tentativi
 */

$start = $booking->time->value;

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Disdici la prenotazione',
    'eyebrow' => 'Il tuo account',
    'heading' => 'Disdici la prenotazione?',
    'lead' => 'Il posto torna libero per un altro. Se poi cambi idea, prenoti di nuovo finché c\'è posto.',
    'compact' => true,
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<dl class="fs-dl fs-dl--stack">
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
  <a href="<?= $view->e(AccountUrls::HOME) ?>" class="fs-btn fs-btn--secondary fs-btn--block">Tienila</a>
</form>
