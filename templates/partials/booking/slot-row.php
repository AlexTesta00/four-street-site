<?php

declare(strict_types=1);

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Slot;
use FourStreet\View\Template;

/**
 * Una fascia nella pagina delle prenotazioni, con lo stesso linguaggio della landing: orario, un quadrato
 * per posto e "3/5", oppure "Completo", "Chiusa" o "La tua". Se il visitatore può sceglierla è un link
 * alla conferma (una riga intera, alta più di 44px); altrimenti una riga ferma. Il nome per i lettori di
 * schermo dice tutto a parole; quadrati e numeri sono solo per gli occhi.
 *
 * @var Template $view
 * @var Slot $slot
 * @var LocalDate $day
 * @var bool $choosable il visitatore può prenotare in questo giorno (non ha già una prenotazione, né il massimo)
 * @var int|null $mySeat il posto del visitatore, se questa fascia è la sua
 */

$taken = $slot->shownTaken();
$link = $choosable && $mySeat === null && $slot->isBookable();
$spoken = match (true) {
    $mySeat !== null => sprintf(', la tua prenotazione, posto %d', $mySeat),
    $slot->closed => ', prenotazioni chiuse',
    $slot->isFull() => ', completa',
    $taken === 1 => sprintf(', 1 posto occupato su %d', $slot->capacity),
    default => sprintf(', %d posti occupati su %d', $taken, $slot->capacity),
};
$modifier = match (true) {
    $mySeat !== null => ' fs-slot__row--mine',
    $link => ' fs-slot__row--link',
    $slot->isBookable() => '',
    default => ' fs-slot__row--off',
};
?>
<li>
<?php if ($link): ?>
  <a href="<?= $view->e(BookingUrls::confirm($day, $slot->startTime())) ?>" class="fs-slot__row<?= $view->e($modifier) ?>">
<?php else: ?>
  <div class="fs-slot__row<?= $view->e($modifier) ?>">
<?php endif; ?>
    <span class="text-body tabular-nums lining-nums"><span class="sr-only">Fascia delle </span><?= $view->e($slot->time) ?><span class="sr-only"><?= $view->e($spoken) ?></span></span>
    <?= $view->partial('partials/booking/seats', ['slot' => $slot, 'mine' => $mySeat]) ?>
<?php if ($mySeat !== null): ?>
    <span class="fs-slot__status fs-tag fs-tag--event" aria-hidden="true">La tua</span>
<?php elseif ($slot->closed): ?>
    <span class="fs-slot__status fs-label text-fg-subtle" aria-hidden="true">Chiusa</span>
<?php elseif ($slot->isFull()): ?>
    <span class="fs-slot__status fs-tag" aria-hidden="true">Completo</span>
<?php else: ?>
    <span class="fs-slot__status fs-label text-fg-muted tabular-nums" aria-hidden="true"><?= $view->e($taken . '/' . $slot->capacity) ?></span>
<?php endif; ?>
<?php if ($link): ?>
    <?= $view->icon('arrow', 'fs-slot__arrow') ?>
  </a>
<?php else: ?>
  </div>
<?php endif; ?>
</li>
