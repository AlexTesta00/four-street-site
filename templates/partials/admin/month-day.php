<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Staff\MonthDay;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * Un giorno nel calendario del mese: una riga-link al giorno del pannello. A sinistra la data come nei calendari,
 * poi quante persone e quante fasce, oppure "Chiuso" (con la nota del titolare, a uso interno: il pannello è solo suo)
 * o "Prima dell'inaugurazione"; sotto, i segni: oggi, le fasce piene, le prenotazioni fuori dall'orario.
 * Quello che si vede è per gli occhi: il nome del link dice tutto a parole, in una frase
 * ("Lunedì 7 dicembre: 6 fasce, 12 prenotati, una fascia piena"). Oggi ha aria-current="date".
 *
 * @var Template $view
 * @var MonthDay $day
 * @var ItalianDateFormatter $dates
 */

$plan = $day->plan;
$changed = $plan->kind === DayKind::Changed;
$closed = $day->isClosed();
$opening = $closed && $day->isBeforeOpening;

if ($opening) {
    $main = 'Prima dell\'inaugurazione';
    $meta = null;
    $spoken = ['prima dell\'inaugurazione'];
} elseif ($closed) {
    // La nota della chiusura, come l'ha scritta il titolare; un giorno con un orario suo senza fasce lo dice.
    $main = 'Chiuso';
    $meta = $plan->note ?? ($changed ? 'Orario cambiato' : null);
    $spoken = $meta === null ? ['chiuso'] : ['chiuso', $meta];
} else {
    [$main, $people] = match ($day->booked) {
        0 => ['Nessuno prenotato', 'nessuno prenotato'],
        1 => ['1 prenotato', '1 prenotato'],
        default => [$day->booked . ' prenotati', $day->booked . ' prenotati'],
    };
    $meta = ($day->slots === 1 ? '1 fascia' : $day->slots . ' fasce') . ($changed ? ' · orario cambiato' : '');
    $spoken = [$day->slots === 1 ? 'una fascia' : $day->slots . ' fasce', $people];
    if ($day->fullSlots > 0) {
        $spoken[] = $day->fullSlots === 1 ? 'una fascia piena' : $day->fullSlots . ' fasce piene';
    }
    if ($changed) {
        $spoken[] = 'orario cambiato';
    }
}
if ($day->outOfSchedule > 0) {
    $spoken[] = $day->outOfSchedule === 1 ? 'una prenotazione fuori dall\'orario' : $day->outOfSchedule . ' prenotazioni fuori dall\'orario';
}

$marks = [];
if ($day->isToday) {
    $marks[] = ['Oggi', 'fs-tag fs-tag--event'];
}
if ($day->fullSlots > 0) {
    $marks[] = [match (true) {
        $day->fullSlots === $day->slots => 'Tutto pieno',
        $day->fullSlots === 1 => '1 piena',
        default => $day->fullSlots . ' piene',
    }, 'fs-tag'];
}
if ($day->outOfSchedule > 0) {
    $marks[] = [$day->outOfSchedule . ' fuori orario', 'fs-tag fs-month__warn'];
}

$classes = 'fs-month__day' . ($closed ? ' fs-month__day--off' : '') . ($day->isPast ? ' fs-month__day--past' : '');
?>
    <li>
      <a href="<?= $view->e(AdminUrls::day($day->date)) ?>" class="<?= $view->e($classes) ?>"<?= $day->isToday ? ' aria-current="date"' : '' ?>>
        <span class="sr-only"><?= $view->e($dates->format($day->date->calendarDay(), withYear: false) . ': ' . implode(', ', $spoken)) ?></span>
        <span class="fs-month__date"><?= $view->partial('partials/booking/day-badge', ['date' => $day->date, 'dates' => $dates, 'withMonth' => false]) ?></span>
        <span class="fs-month__body" aria-hidden="true">
          <span class="fs-month__main"><?= $view->e($main) ?></span>
<?php if ($meta !== null): ?>
          <span class="fs-month__meta"><?= $view->e($meta) ?></span>
<?php endif; ?>
<?php if ($marks !== []): ?>
          <span class="fs-month__marks"><?php foreach ($marks as [$label, $class]): ?><span class="<?= $view->e($class) ?>"><?= $view->e($label) ?></span><?php endforeach; ?></span>
<?php endif; ?>
        </span>
        <?= $view->icon('next', 'fs-month__arrow') ?>
      </a>
    </li>
