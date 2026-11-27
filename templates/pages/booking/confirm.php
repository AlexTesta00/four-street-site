<?php

declare(strict_types=1);

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\Slot;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /prenota/conferma/ con GET: il riepilogo della fascia scelta e un solo bottone, "Conferma la prenotazione"
 * (POST con il token CSRF; lo script blocca il secondo tocco). Il posto è quello che toccherebbe adesso:
 * se nel frattempo lo prende un altro, la conferma dà il primo libero.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var ScheduledSlot $slot
 * @var Slot $seats i posti della fascia, con quelli presi
 * @var string $day "Lunedì 7 dicembre"
 * @var string $email dove arriva la conferma
 * @var string $cancellation fino a quando si disdice: "fino all'inizio della fascia"
 * @var string $backUrl le fasce dello stesso giorno
 * @var Alert|null $alert troppi tentativi
 */

$seat = (int) $seats->nextSeat();
$start = $slot->startsAt->format('H:i');
$end = $slot->endsAt->format('H:i');

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Conferma la prenotazione',
    'eyebrow' => 'Prenotazione',
    'heading' => $day . ', ore ' . $start . '.',
    'lead' => null,
    'compact' => true,
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<dl class="fs-dl fs-dl--stack">
  <div class="fs-dl__row">
    <dt class="fs-label">Orario</dt>
    <dd><span class="sr-only"><?= $view->e('Dalle ' . $start . ' alle ' . $end) ?></span><span aria-hidden="true"><?= $view->e($start) ?> – <?= $view->e($end) ?></span></dd>
  </div>
  <div class="fs-dl__row">
    <dt class="fs-label">Posto</dt>
    <dd class="flex flex-wrap items-center gap-x-4 gap-y-2">
      <span><?= $view->e($seat) ?> di <?= $view->e($seats->capacity) ?></span>
      <?= $view->partial('partials/booking/seats', ['slot' => $seats, 'mine' => $seat]) ?>
    </dd>
  </div>
  <div class="fs-dl__row">
    <dt class="fs-label">Dove</dt>
    <dd><?= $view->e($site->address) ?></dd>
  </div>
</dl>
<form method="post" action="<?= $view->e(BookingUrls::CONFIRM) ?>" class="fs-actions" data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
  <input type="hidden" name="<?= $view->e(BookingUrls::DAY_PARAMETER) ?>" value="<?= $view->e($slot->date->value) ?>">
  <input type="hidden" name="<?= $view->e(BookingUrls::TIME_PARAMETER) ?>" value="<?= $view->e($slot->time->value) ?>">
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Conferma la prenotazione</button>
  <a href="<?= $view->e($backUrl) ?>" class="fs-btn fs-btn--secondary fs-btn--block">Cambia fascia</a>
</form>
<div class="fs-auth__note">
  <?= $view->icon('mail', 'fs-auth__note-icon') ?>
  <p>Ti mandiamo la conferma a <?= $view->e($email) ?>, con l'evento da aggiungere al calendario. Se non puoi venire, disdici dal tuo account <?= $view->e($cancellation) ?>: il posto torna libero per un altro.</p>
</div>
