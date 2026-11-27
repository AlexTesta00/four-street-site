<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Staff\WeekSheet;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * Come si cambia settimana nell'orario: la settimana prima e quella dopo (fino al bordo della portata del pannello) e,
 * sotto, "Questa settimana" quando se ne guarda un'altra. Lo stesso componente della navigazione fra i giorni
 * (fs-daynav), su due righe come quella fra i mesi (fs-hours__nav): "Questa settimana" fra le frecce non ci sta sul
 * telefono. Le frecce mostrano il lunedì abbreviato ("30 nov"), che resta nel nome del link (chi usa i comandi vocali
 * dice quello che vede); i lettori di schermo sentono anche la settimana per intero.
 *
 * @var Template $view
 * @var WeekSheet $sheet
 * @var ScheduleDates $days
 * @var ItalianDateFormatter $dates
 */

$short = static fn(LocalDate $monday): string => $monday->calendarDay()->format('j') . ' ' . $dates->shortMonth($monday->calendarDay());
$spoken = static fn(LocalDate $monday): string => ', ' . $days->span($monday, $monday->addDays(6));
?>
<nav class="fs-daynav fs-hours__nav" aria-label="Settimana prima e dopo">
<?php if ($sheet->previous !== null): ?>
  <a href="<?= $view->e(AdminUrls::week($sheet->previous)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step">
    <?= $view->icon('previous') ?>
    <span><span class="sr-only">Settimana prima: </span><?= $view->e($short($sheet->previous)) ?><span class="sr-only"><?= $view->e($spoken($sheet->previous)) ?></span></span>
  </a>
<?php endif; ?>
<?php if ($sheet->next !== null): ?>
  <a href="<?= $view->e(AdminUrls::week($sheet->next)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step fs-daynav__step--next">
    <span><span class="sr-only">Settimana dopo: </span><?= $view->e($short($sheet->next)) ?><span class="sr-only"><?= $view->e($spoken($sheet->next)) ?></span></span>
    <?= $view->icon('next') ?>
  </a>
<?php endif; ?>
<?php if (!$sheet->isCurrent()): ?>
  <a href="<?= $view->e(AdminUrls::week()) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__today">Questa settimana</a>
<?php endif; ?>
</nav>
