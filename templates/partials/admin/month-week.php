<?php

declare(strict_types=1);

use FourStreet\Booking\Staff\MonthDay;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * Una settimana del calendario del mese, da lunedì a domenica (la prima e l'ultima del mese possono essere più corte):
 * il titoletto ("Dal 7 al 13 dicembre", "Dall'8 al 14 febbraio"; un giorno solo, "Lunedì 31 agosto") e un giorno
 * per riga (partials/admin/month-day).
 *
 * @var Template $view
 * @var non-empty-list<MonthDay> $week
 * @var string $monthWord il mese a parole, "dicembre"
 * @var ItalianDateFormatter $dates
 */

$first = $week[0];
$last = $week[count($week) - 1];
$from = (int) $first->date->calendarDay()->format('j');
$to = (int) $last->date->calendarDay()->format('j');
// "dal 7", ma "dall'8" e "dall'11": l'articolo si elide davanti a un numero che si legge con la vocale.
$elided = static fn(int $number): bool => in_array($number, [8, 11], true);
$title = count($week) === 1
    ? $dates->format($first->date->calendarDay(), withYear: false)
    : ($elided($from) ? 'Dall\'' . $from : 'Dal ' . $from) . ($elided($to) ? ' all\'' . $to : ' al ' . $to) . ' ' . $monthWord;
$id = 'settimana-' . $first->date->value;
?>
<section class="fs-month__week" aria-labelledby="<?= $view->e($id) ?>">
  <h2 id="<?= $view->e($id) ?>" class="fs-month__title fs-label"><?= $view->e($title) ?></h2>
  <ul class="fs-month__days">
<?php foreach ($week as $day): ?>
<?= $view->partial('partials/admin/month-day', ['day' => $day, 'dates' => $dates]) ?>
<?php endforeach; ?>
  </ul>
</section>
