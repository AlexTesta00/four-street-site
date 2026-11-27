<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\TimeField;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleChanges;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\WeekController;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Booking\Staff\WeekSheet;
use FourStreet\Booking\Staff\WeekSheetDay;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * Un giorno di una settimana precisa, in un details (si apre anche senza JavaScript). Chiuso mostra la data come nei
 * calendari, quante fasce e quante persone, da dove viene l'orario ("Settimana tipo", "Orario cambiato", "Chiuso ·
 * ferie") e i segni: oggi, il giorno passato, le prenotazioni fuori dall'orario. Aperto, gli orari con le persone
 * prenotate ("18:30 · 3") e le azioni che il giorno permette adesso (WeekSheetDay): togliere e aggiungere un orario,
 * chiudere con la nota, riaprire, tornare alla settimana tipo; in fondo il link al giorno del pannello, dove si vede
 * chi è prenotato. Un giorno passato, o prima dell'inaugurazione, si guarda soltanto.
 *
 * Il details ha l'id del giorno ("giorno-2026-12-09"): dopo una modifica la pagina torna qui, aperto, con l'avviso;
 * gli errori del giorno stanno qui dentro, e il giorno con un errore è aperto.
 *
 * @var Template $view
 * @var string $csrf
 * @var WeekSheetDay $day
 * @var WeekSheet $sheet
 * @var bool $open il giorno si apre da solo
 * @var ChangeForm $form il modulo inviato: se è di questo giorno, si ricompila
 * @var FormErrors $errors
 * @var ChangeReport|null $report l'avviso dopo una modifica di questo giorno
 * @var ScheduleDates $days
 * @var ItalianDateFormatter $dates
 */

$date = $day->date;
$id = WeekController::dayId($date);
$timeId = WeekController::timeId($date);
$noteId = WeekController::noteId($date);
$action = AdminUrls::week($sheet->monday, $date);
$plan = $day->plan;
$closed = $plan->isClosed();
$count = count($day->times);
$inSchedule = $day->booked() - $day->outOfSchedule;
$named = $days->day($date);
$isOpen = $open || $errors->has($id) || $errors->has($timeId) || $errors->has($noteId);

// In poche parole, per il summary: le fasce e le persone, da dove viene l'orario, i segni.
$people = match ($inSchedule) {
    0 => 'nessuno prenotato',
    1 => '1 prenotato',
    default => $inSchedule . ' prenotati',
};
if ($day->isBeforeOpening) {
    [$main, $source, $spoken] = ['Prima dell\'inaugurazione', null, ['prima dell\'inaugurazione']];
} elseif ($closed) {
    $main = 'Chiuso' . ($plan->note === null ? '' : ' · ' . $plan->note);
    [$source, $spoken] = [null, $plan->note === null ? ['chiuso'] : ['chiuso', $plan->note]];
} else {
    $slots = match ($count) {
        0 => 'Nessuna fascia',
        1 => '1 fascia',
        default => $count . ' fasce',
    };
    $main = $slots . ($inSchedule > 0 ? ' · ' . $people : '');
    $source = $plan->kind === DayKind::Changed ? 'Orario cambiato' : 'Settimana tipo';
    $spoken = $count === 0 ? ['nessuna fascia', lcfirst($source)] : [lcfirst($slots), $people, lcfirst($source)];
}
$marks = [];
if ($day->isToday) {
    $marks[] = ['Oggi', 'fs-tag fs-tag--event'];
    $spoken[] = 'oggi';
}
if ($day->isPast) {
    $marks[] = ['Passato', 'fs-tag'];
    $spoken[] = 'passato';
}
if ($day->outOfSchedule > 0) {
    $marks[] = [$day->outOfSchedule . ' fuori orario', 'fs-tag fs-hours__warn'];
    $spoken[] = $day->outOfSchedule === 1 ? 'una prenotazione fuori dall\'orario' : $day->outOfSchedule . ' prenotazioni fuori dall\'orario';
}

$classes = 'fs-hours__fold'
    . ($day->isToday ? ' fs-hours__fold--today' : '')
    . ($day->isPast ? ' fs-hours__fold--past' : '')
    . ($closed || $day->isBeforeOpening || $count === 0 ? ' fs-hours__fold--off' : '');
$state = match (true) {
    $day->isBeforeOpening => 'Prima dell\'inaugurazione: nessuna fascia.',
    $day->isPast => 'Passato: si guarda soltanto.',
    $closed => $plan->note === null ? 'Chiuso tutto il giorno.' : 'Chiuso tutto il giorno: ' . $plan->note,
    default => null,
};
$weekTimes = ScheduleChanges::times($day->weekTimes);
?>
<details class="<?= $view->e($classes) ?>" id="<?= $view->e($id) ?>"<?= $isOpen ? ' open' : '' ?>>
  <summary class="fs-hours__summary">
    <span class="sr-only"><?= $view->e($dates->format($date->calendarDay(), withYear: false) . ': ' . implode(', ', $spoken)) ?></span>
    <span class="fs-hours__date"><?= $view->partial('partials/booking/day-badge', ['date' => $date, 'dates' => $dates, 'withMonth' => true]) ?></span>
    <span class="fs-hours__about" aria-hidden="true">
      <span class="fs-hours__main"><?= $view->e($main) ?></span>
<?php if ($source !== null): ?>
      <span class="fs-hours__meta"><?= $view->e($source) ?></span>
<?php endif; ?>
<?php if ($marks !== []): ?>
      <span class="fs-hours__marks"><?php foreach ($marks as [$label, $class]): ?><span class="<?= $view->e($class) ?>"><?= $view->e($label) ?></span><?php endforeach; ?></span>
<?php endif; ?>
    </span>
    <?= $view->icon('plus', 'fs-hours__toggle') ?>
  </summary>
  <div class="fs-hours__body">
<?php if ($report !== null): ?>
<?= $view->partial('partials/admin/change-notice', ['report' => $report, 'days' => $days]) ?>
<?php endif; ?>
<?php if ($errors->has($id)): ?>
    <?= $view->partial('partials/admin/change-error', ['id' => $id, 'error' => (string) $errors->get($id)]) ?>
<?php endif; ?>
<?php if ($state !== null): ?>
    <p class="fs-hours__status"><?= $view->e($state) ?></p>
<?php endif; ?>
<?php if ($day->times !== []): ?>
    <ul class="fs-hours__times fs-hours__times--booked" aria-label="<?= $view->e('Orari di ' . $named) ?>">
<?php foreach ($day->times as $time):
    $removable = $day->canRemove($time);
    ?>
<?= $view->partial('partials/admin/hours-chip', [
        'csrf' => $csrf,
        'time' => $time->time->value,
        'booked' => $time->booked,
        'state' => match (true) {
            // Non c'è: prima dell'apertura, il giorno dell'inaugurazione; altrimenti è l'ora che il cambio dell'ora salta.
            !$time->exists => $date->equals($sheet->openingDay) ? 'Prima dell\'inaugurazione' : 'Cambio dell\'ora',
            $time->started && !$day->isPast => 'Cominciata',
            default => null,
        },
        'missing' => !$time->exists,
        'action' => $removable ? $action : null,
        'fields' => [ChangeForm::ACTION => ChangeAction::Remove->value, ChangeForm::DAY => $date->value, ChangeForm::TIME => $time->time->value],
        'label' => sprintf('Togli le %s di %s', $time->time->value, $named),
    ]) ?>
<?php endforeach; ?>
    </ul>
<?php elseif ($state === null): ?>
    <p class="fs-hours__status"><?= $view->e($day->weekTimes === [] ? 'Nessuna fascia: nella settimana tipo è chiuso.' : 'Nessuna fascia.') ?></p>
<?php endif; ?>
<?php if ($day->canAdd()): ?>
    <?= $view->partial('partials/admin/hours-add', [
        'csrf' => $csrf,
        'action' => $action,
        'fields' => [ChangeForm::ACTION => ChangeAction::Add->value, ChangeForm::DAY => $date->value],
        'id' => $timeId,
        'value' => $form->isFor(ChangeAction::Add, $date->value) ? TimeField::refill($form->time) : '',
        'error' => $errors->get($timeId),
        'for' => 'a ' . $named,
    ]) ?>
<?php endif; ?>
<?php if ($day->canClose() || $day->canReopen() || $day->canGoBackToWeek()): ?>
    <div class="fs-hours__actions">
<?php if ($day->canGoBackToWeek()): ?>
      <form method="post" action="<?= $view->e($action) ?>" class="fs-hours__action" data-submit-once>
        <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => [ChangeForm::ACTION => ChangeAction::BackToWeek->value, ChangeForm::DAY => $date->value]]) ?>
        <button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="<?= $view->e('Torna alla settimana tipo, ' . $named) ?>" aria-describedby="<?= $view->e($id) ?>-settimana-tipo">Torna alla settimana tipo</button>
        <p class="fs-field__hint" id="<?= $view->e($id) ?>-settimana-tipo"><?= $view->e(ucfirst($weekTimes) . '.') ?></p>
      </form>
<?php endif; ?>
<?php if ($day->canReopen()): ?>
      <form method="post" action="<?= $view->e($action) ?>" class="fs-hours__action" data-submit-once>
        <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => [ChangeForm::ACTION => ChangeAction::Reopen->value, ChangeForm::DAY => $date->value]]) ?>
        <button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="<?= $view->e('Riapri il giorno, ' . $named) ?>" aria-describedby="<?= $view->e($id) ?>-riapri">Riapri il giorno</button>
        <p class="fs-field__hint" id="<?= $view->e($id) ?>-riapri"><?= $view->e('Torna alla settimana tipo: ' . $weekTimes . '.') ?></p>
      </form>
<?php endif; ?>
<?php if ($day->canClose()):
    $noteError = $errors->get($noteId);
    $noteDescribedBy = $noteId . '-aiuto' . ($noteError === null ? '' : ' ' . $noteId . '-errore');
    ?>
      <form method="post" action="<?= $view->e($action) ?>" class="fs-hours__action" novalidate data-submit-once>
        <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => [ChangeForm::ACTION => ChangeAction::Close->value, ChangeForm::DAY => $date->value]]) ?>
        <div class="fs-field">
          <label class="fs-field__label fs-label" for="<?= $view->e($noteId) ?>">Nota della chiusura<span class="sr-only"><?= $view->e(', ' . $named) ?></span></label>
          <p class="fs-field__hint" id="<?= $view->e($noteId) ?>-aiuto">Facoltativa, per esempio: ferie. La vedi solo tu, mai i clienti.</p>
<?php if ($noteError !== null): ?>
          <p class="fs-field__error" id="<?= $view->e($noteId) ?>-errore"><?= $view->icon('alert') ?><span><span class="sr-only">Errore: </span><?= $view->e($noteError) ?></span></p>
<?php endif; ?>
          <input class="fs-input" id="<?= $view->e($noteId) ?>" name="<?= $view->e(ChangeForm::NOTE) ?>" type="text" value="<?= $view->e($form->isFor(ChangeAction::Close, $date->value) ? $form->note : '') ?>"<?= $view->attributes([
              'maxlength' => (string) ScheduleChange::NOTE_MAX_LENGTH,
              'autocomplete' => 'off',
              'aria-invalid' => $noteError !== null ? 'true' : null,
              'aria-describedby' => $noteDescribedBy,
          ]) ?>>
        </div>
        <button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm fs-btn--block" aria-label="<?= $view->e('Chiudi il giorno, ' . $named) ?>">Chiudi il giorno</button>
      </form>
<?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($day->outOfSchedule > 0): ?>
    <div class="fs-alert fs-alert--error">
      <?= $view->icon('alert', 'fs-alert__icon') ?>
      <div class="fs-alert__body">
        <p class="fs-alert__title">Da guardare</p>
        <p><?= $view->e($day->outOfSchedule === 1
            ? 'Una prenotazione è in una fascia che l\'orario non ha più: la trovi in «Chi è prenotato», e da lì la disdici.'
            : sprintf('%d prenotazioni sono in fasce che l\'orario non ha più: le trovi in «Chi è prenotato», e da lì le disdici.', $day->outOfSchedule)) ?></p>
      </div>
    </div>
<?php endif; ?>
<?php if (!$day->isBeforeOpening): ?>
    <a href="<?= $view->e(AdminUrls::day($date)) ?>" class="fs-hours__link">
      <span>Chi è prenotato<span class="sr-only"><?= $view->e(', ' . $named) ?></span></span>
      <?= $view->icon('next') ?>
    </a>
<?php endif; ?>
  </div>
</details>
