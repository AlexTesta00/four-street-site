<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\Staff\Month;
use FourStreet\Booking\Staff\MonthSheet;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * Come si cambia mese nel calendario del pannello: il mese prima e quello dopo (fino al bordo della portata del
 * pannello) e, sotto, "Questo mese" quando se ne guarda un altro. Lo stesso componente della navigazione fra i giorni
 * (fs-daynav), su due righe (fs-month__nav): "Questo mese" è troppo lungo per stare fra le frecce sul telefono.
 * L'ordine nella pagina è quello che si vede, anche per chi va con il tasto Tab. Le frecce mostrano il mese abbreviato
 * ("nov"), che resta nel nome del link (chi usa i comandi vocali dice quello che vede); i lettori di schermo sentono
 * anche il mese per intero, con l'anno.
 *
 * @var Template $view
 * @var MonthSheet $sheet
 * @var string|null $previousName "novembre 2026"
 * @var string|null $nextName "gennaio 2027"
 * @var ItalianDateFormatter $dates
 */

$short = static fn(Month $month): string => $dates->shortMonth($month->first()->calendarDay());
?>
<nav class="fs-daynav fs-month__nav" aria-label="Mese prima e dopo">
<?php if ($sheet->previous !== null): ?>
  <a href="<?= $view->e(AdminUrls::calendar($sheet->previous)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step">
    <?= $view->icon('previous') ?>
    <span><span class="sr-only">Mese prima: </span><?= $view->e($short($sheet->previous)) ?><span class="sr-only"><?= $view->e(', ' . $previousName) ?></span></span>
  </a>
<?php endif; ?>
<?php if ($sheet->next !== null): ?>
  <a href="<?= $view->e(AdminUrls::calendar($sheet->next)) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__step fs-daynav__step--next">
    <span><span class="sr-only">Mese dopo: </span><?= $view->e($short($sheet->next)) ?><span class="sr-only"><?= $view->e(', ' . $nextName) ?></span></span>
    <?= $view->icon('next') ?>
  </a>
<?php endif; ?>
<?php if (!$sheet->isCurrent()): ?>
  <a href="<?= $view->e(AdminUrls::calendar()) ?>" class="fs-btn fs-btn--secondary fs-btn--sm fs-daynav__today">Questo mese</a>
<?php endif; ?>
</nav>
