<?php

declare(strict_types=1);

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\DayBoard;
use FourStreet\Booking\DayState;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * La scelta del giorno: un link per ogni giorno prenotabile (?giorno=AAAA-MM-GG), senza JavaScript.
 * Il giorno mostrato ha aria-current; i giorni senza fasce da prenotare non sono link. Un punto oro segna
 * i giorni in cui il visitatore ha già una prenotazione. I lettori di schermo sentono il giorno per intero.
 *
 * @var Template $view
 * @var DayBoard $board
 * @var ItalianDateFormatter $dates
 */
?>
<nav aria-label="Giorni" class="fs-days">
  <ul class="fs-days__list">
<?php foreach ($board->days as $index => $option):
    $day = $option->date->calendarDay();
    $state = $option->state();
    $current = $board->selected !== null && $option->date->equals($board->selected->date);
    $spoken = $dates->format($day, withYear: false) . match ($state) {
        DayState::Open => '',
        DayState::Full => ', al completo',
        DayState::Closed => $option->hasSlots() ? ', prenotazioni chiuse' : ', nessuna fascia',
    } . ($option->mine === null ? '' : ', hai una prenotazione alle ' . $option->mine->time->value);
    $face = $view->partial('partials/booking/day-badge', ['date' => $option->date, 'dates' => $dates, 'withMonth' => $index === 0 || $day->format('j') === '1']);
    $mark = $option->mine === null ? '' : '<span class="fs-day__mark" aria-hidden="true"></span>';
    $classes = 'fs-day' . ($state === DayState::Open ? '' : ' fs-day--quiet');
    ?>
    <li>
<?php if ($state === DayState::Closed && !$current): ?>
      <span class="<?= $view->e($classes) ?> fs-day--off"><?= $face ?><span class="sr-only"><?= $view->e($spoken) ?></span><?= $mark ?></span>
<?php else: ?>
      <a href="<?= $view->e(BookingUrls::day($option->date)) ?>" class="<?= $view->e($classes) ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= $face ?><span class="sr-only"><?= $view->e($spoken) ?></span><?= $mark ?></a>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
</nav>
