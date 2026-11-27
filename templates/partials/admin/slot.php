<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\Staff\RosterSlot;
use FourStreet\Booking\Staff\SlotPhase;
use FourStreet\View\Template;

/**
 * Una fascia nel pannello del titolare: l'orario grande, i posti presi (i quadrati e "3/5") e chi c'è, dal posto 1,
 * con il nome, l'email (solo per un cliente registrato) e se l'ha inserita la palestra. Finché la fascia non è
 * iniziata ogni prenotazione ha "Disdici" e, se c'è posto, in fondo c'è "Aggiungi": portano a una conferma e a un
 * modulo, mai a un cambiamento immediato. Una fascia in corso o finita si guarda soltanto.
 *
 * @var Template $view
 * @var RosterSlot $slot
 * @var string $day il giorno, per i nomi dei link: "lunedì 7 dicembre"
 */

$time = $slot->slot->time->value;
$end = $slot->slot->endsAt->format('H:i');
$taken = $slot->taken();
$headingId = 'fascia-' . str_replace(':', '', $time);
$spoken = sprintf(', fino alle %s: ', $end) . match (true) {
    $taken === 0 => 'nessuno prenotato',
    $slot->isFull() => sprintf('completa, %d su %d', $taken, $slot->capacity),
    $taken === 1 => sprintf('1 posto occupato su %d', $slot->capacity),
    default => sprintf('%d posti occupati su %d', $taken, $slot->capacity),
};
[$state, $tag] = match (true) {
    $slot->phase === SlotPhase::InProgress => ['now', ['In corso', 'fs-tag fs-tag--event']],
    $slot->phase === SlotPhase::Over => ['over', ['Finita', 'fs-tag']],
    !$slot->scheduled => ['', ['Fuori dall\'orario', 'fs-tag']],
    $slot->isFull() => ['', ['Completa', 'fs-tag']],
    default => ['', null],
};
?>
<section class="fs-roster<?= $state === '' ? '' : ' fs-roster--' . $view->e($state) ?>" aria-labelledby="<?= $view->e($headingId) ?>">
  <div class="fs-roster__head">
    <h2 id="<?= $view->e($headingId) ?>" class="fs-roster__time"><span class="sr-only">Fascia delle </span><?= $view->e($time) ?><span class="sr-only"><?= $view->e($spoken) ?></span></h2>
    <div class="fs-roster__fill" aria-hidden="true">
      <?= $view->partial('partials/booking/seats', ['slot' => $slot->seats(), 'mine' => null, 'medium' => true]) ?>
      <span class="fs-roster__count"><?= $view->e($taken . '/' . $slot->capacity) ?></span>
    </div>
  </div>
<?php if ($tag !== null): ?>
  <p class="fs-roster__state"><span class="<?= $view->e($tag[1]) ?>"><?php if ($state === 'now'): ?><span class="fs-tag__dot fs-anim-breathe"></span><?php endif; ?><?= $view->e($tag[0]) ?></span></p>
<?php endif; ?>
<?php if ($slot->entries === []): ?>
  <p class="fs-roster__empty">Nessuno prenotato.</p>
<?php else: ?>
  <ul class="fs-roster__people" aria-label="<?= $view->e('Prenotati alle ' . $time) ?>">
<?php foreach ($slot->entries as $entry):
    $booking = $entry->booking;
    $meta = ($entry->email === null ? 'Senza account' : $entry->email->value)
        . ($booking->createdBy === CreatedBy::Admin ? ' · inserita dalla palestra' : '');
    ?>
    <li class="fs-roster__person">
      <span class="fs-roster__seat" aria-hidden="true"><?= $view->e($booking->seat()) ?></span>
      <p class="fs-roster__name"><span class="sr-only"><?= $view->e(sprintf('Posto %d: ', $booking->seat())) ?></span><?= $view->e($entry->name) ?></p>
      <p class="fs-roster__meta"><?= $view->e($meta) ?></p>
<?php if ($slot->canCancel()): ?>
      <a href="<?= $view->e(AdminUrls::cancel($booking->id)) ?>" class="fs-btn fs-btn--ghost fs-btn--sm fs-roster__action" aria-label="<?= $view->e(sprintf('Disdici la prenotazione di %s, %s alle %s', $entry->name, $day, $time)) ?>"><span class="fs-btn__label">Disdici</span></a>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if ($slot->canAdd()): ?>
  <a href="<?= $view->e(AdminUrls::newBooking($slot->slot->date, $slot->slot->time)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="<?= $view->e(sprintf('Aggiungi una persona, %s alle %s', $day, $time)) ?>">
    <?= $view->icon('plus') ?>
    <span>Aggiungi</span>
  </a>
<?php endif; ?>
</section>
