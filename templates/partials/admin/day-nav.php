<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Staff\RosterDay;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * Come si cambia giorno nel pannello: il giorno prima e quello dopo (anche nel passato, fino al bordo della portata
 * del pannello) con, in mezzo, "Oggi" quando si guarda un altro giorno; sotto, la scelta rapida dei giorni
 * prenotabili, come in /prenota/, dentro un details chiuso (si apre anche senza JavaScript) così le fasce restano
 * nel primo schermo del telefono. Le frecce mostrano il giorno abbreviato ("gio 26"), che resta nel nome del link
 * (chi usa i comandi vocali dice quello che vede); i lettori di schermo sentono anche il giorno per intero.
 *
 * @var Template $view
 * @var RosterDay $roster
 * @var ItalianDateFormatter $dates
 */

$short = static fn(LocalDate $date): string => $dates->shortWeekday($date->calendarDay()) . ' ' . $date->calendarDay()->format('j');
$spoken = static fn(LocalDate $date): string => $dates->format($date->calendarDay(), withYear: false, capitalize: false);
?>
<nav class="fs-daynav" aria-label="Giorno prima e dopo">
<?php if ($roster->previous !== null): ?>
  <a href="<?= $view->e(AdminUrls::day($roster->previous)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step">
    <?= $view->icon('previous') ?>
    <span><span class="sr-only">Giorno prima: </span><?= $view->e($short($roster->previous)) ?><span class="sr-only"><?= $view->e(', ' . $spoken($roster->previous)) ?></span></span>
  </a>
<?php endif; ?>
<?php if (!$roster->isToday()): ?>
  <a href="<?= $view->e(AdminUrls::day($roster->today)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__today">Oggi</a>
<?php endif; ?>
<?php if ($roster->next !== null): ?>
  <a href="<?= $view->e(AdminUrls::day($roster->next)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step fs-daynav__step--next">
    <span><span class="sr-only">Giorno dopo: </span><?= $view->e($short($roster->next)) ?><span class="sr-only"><?= $view->e(', ' . $spoken($roster->next)) ?></span></span>
    <?= $view->icon('next') ?>
  </a>
<?php endif; ?>
</nav>
<?php if ($roster->choices !== []): ?>
<details class="fs-daypicker">
  <summary class="fs-daypicker__toggle">
    <span class="fs-label">Scegli il giorno</span>
    <?= $view->icon('plus', 'fs-daypicker__icon') ?>
  </summary>
  <div class="fs-daypicker__body">
    <?= $view->partial('partials/admin/days', ['roster' => $roster, 'dates' => $dates]) ?>
  </div>
</details>
<?php endif; ?>
