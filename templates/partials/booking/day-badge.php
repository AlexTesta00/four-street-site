<?php

declare(strict_types=1);

use FourStreet\Booking\LocalDate;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * Un giorno come nei calendari: il giorno della settimana, il numero e il mese abbreviati ("LUN 7 DIC").
 * Solo per gli occhi: chi lo usa scrive vicino il giorno per intero per i lettori di schermo.
 *
 * @var Template $view
 * @var LocalDate $date
 * @var ItalianDateFormatter $dates
 * @var bool $withMonth il mese sotto il numero (sempre negli elenchi; nella scelta dei giorni solo quando cambia)
 */

$day = $date->calendarDay();
?>
<span class="fs-day__face" aria-hidden="true">
  <span class="fs-day__weekday"><?= $view->e($dates->shortWeekday($day)) ?></span>
  <span class="fs-day__number"><?= $view->e($day->format('j')) ?></span>
<?php if ($withMonth): ?>
  <span class="fs-day__month"><?= $view->e($dates->shortMonth($day)) ?></span>
<?php endif; ?>
</span>
