<?php

declare(strict_types=1);

use FourStreet\Booking\Slot;
use FourStreet\View\Template;

/**
 * Una fascia nel gruppo di radio: orario, un quadrato per posto e quanti sono presi ("3/5"),
 * oppure "Completo" o "Chiusa" (e allora non si può scegliere). Il nome per i lettori di schermo dice
 * tutto a parole; quadrati e numeri sono solo per gli occhi. data-seat è il posto che sarebbe tuo
 * (il primo libero): lo script lo scrive nel riepilogo quando la scelta cambia.
 *
 * @var Template $view
 * @var Slot $slot
 * @var bool $checked
 */

$nextSeat = $slot->nextSeat();
$taken = $slot->shownTaken();
$spoken = match (true) {
    $slot->closed => ', prenotazioni chiuse',
    $nextSeat === null => ', completa',
    $taken === 1 => sprintf(', 1 posto occupato su %d', $slot->capacity),
    default => sprintf(', %d posti occupati su %d', $taken, $slot->capacity),
};
?>
<label class="fs-slot">
  <input type="radio" name="fascia" value="<?= $view->e($slot->time) ?>" class="fs-slot__input"<?= $nextSeat === null ? ' disabled' : ' data-seat="' . $view->e($nextSeat) . '"' ?><?= $checked ? ' checked' : '' ?>>
  <span class="fs-slot__row">
    <span class="text-body tabular-nums lining-nums"><span class="sr-only">Fascia delle </span><?= $view->e($slot->time) ?><span class="sr-only"><?= $view->e($spoken) ?></span></span>
    <?= $view->partial('partials/booking/seats', ['slot' => $slot, 'mine' => null]) ?>
<?php if ($slot->closed): ?>
    <span class="fs-slot__status fs-label text-fg-subtle" aria-hidden="true">Chiusa</span>
<?php elseif ($nextSeat === null): ?>
    <span class="fs-slot__status fs-tag" aria-hidden="true">Completo</span>
<?php else: ?>
    <span class="fs-slot__status fs-label text-fg-muted tabular-nums" aria-hidden="true"><?= $view->e($taken . '/' . $slot->capacity) ?></span>
<?php endif; ?>
  </span>
</label>
