<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\Staff\RosterDay;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * La scelta rapida dei giorni nel pannello, con le caselle di /prenota/ (fs-days): un link per ogni giorno da oggi
 * ai giorni prenotabili. Il giorno mostrato ha aria-current; un giorno senza fasce (domenica, chiusura) non è un link.
 * I lettori di schermo sentono il giorno per intero.
 *
 * @var Template $view
 * @var RosterDay $roster
 * @var ItalianDateFormatter $dates
 */
?>
<nav aria-label="Giorni" class="fs-days">
  <ul class="fs-days__list">
<?php foreach ($roster->choices as $index => $choice):
    $day = $choice->date->calendarDay();
    $current = $choice->date->equals($roster->date);
    $spoken = $dates->format($day, withYear: false)
        . ($choice->date->equals($roster->today) ? ', oggi' : '')
        . ($choice->hasSlots ? '' : ', nessuna fascia');
    $face = $view->partial('partials/booking/day-badge', ['date' => $choice->date, 'dates' => $dates, 'withMonth' => $index === 0 || $day->format('j') === '1']);
    ?>
    <li>
<?php if (!$choice->hasSlots && !$current): ?>
      <span class="fs-day fs-day--quiet fs-day--off"><?= $face ?><span class="sr-only"><?= $view->e($spoken) ?></span></span>
<?php else: ?>
      <a href="<?= $view->e(AdminUrls::day($choice->date)) ?>" class="fs-day<?= $choice->hasSlots ? '' : ' fs-day--quiet' ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= $face ?><span class="sr-only"><?= $view->e($spoken) ?></span></a>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
</nav>
