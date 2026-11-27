<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\TimeField;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\WeekTemplateController;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Http\Form\FormErrors;
use FourStreet\View\Template;

/**
 * Un giorno della settimana tipo, una scheda: il nome, gli orari come chip (ognuno con il bottone che lo toglie) oppure
 * "Chiuso: nessuna fascia", e in fondo "Aggiungi un orario". L'id della scheda ("giorno-1") è fisso: dopo una modifica
 * la pagina torna qui, e qui stanno l'avviso e l'errore che riguardano il giorno.
 *
 * @var Template $view
 * @var string $csrf
 * @var Weekday $day
 * @var list<SlotTime> $times gli orari della settimana tipo per quel giorno, dal più presto
 * @var ChangeForm $form il modulo inviato: se è di questo giorno, si ricompila
 * @var FormErrors $errors
 * @var ChangeReport|null $report l'avviso dopo una modifica di questo giorno
 * @var ScheduleDates $days
 */

$id = WeekTemplateController::dayId($day);
$timeId = WeekTemplateController::timeId($day);
$count = count($times);
$value = $form->isFor(ChangeAction::Add, (string) $day->value) ? TimeField::refill($form->time) : '';
?>
<section class="fs-hours__day" id="<?= $view->e($id) ?>" aria-labelledby="<?= $view->e($id) ?>-nome">
  <div class="fs-hours__head">
    <h2 id="<?= $view->e($id) ?>-nome" class="fs-hours__name"><?= $view->e(ucfirst($day->label())) ?></h2>
<?php if ($count > 0): ?>
    <p class="fs-hours__total"><?= $view->e($count === 1 ? '1 fascia' : $count . ' fasce') ?></p>
<?php endif; ?>
  </div>
<?php if ($report !== null): ?>
<?= $view->partial('partials/admin/change-notice', ['report' => $report, 'days' => $days]) ?>
<?php endif; ?>
<?php if ($errors->has($id)): ?>
  <?= $view->partial('partials/admin/change-error', ['id' => $id, 'error' => (string) $errors->get($id)]) ?>
<?php endif; ?>
<?php if ($times === []): ?>
  <p class="fs-hours__status">Chiuso: nessuna fascia.</p>
<?php else: ?>
  <ul class="fs-hours__times" aria-label="<?= $view->e('Orari ' . ($day === Weekday::Sunday ? 'della ' : 'del ') . $day->label()) ?>">
<?php foreach ($times as $time): ?>
<?= $view->partial('partials/admin/hours-chip', [
    'csrf' => $csrf,
    'time' => $time->value,
    'booked' => null,
    'state' => null,
    'missing' => false,
    'action' => AdminUrls::SCHEDULE,
    'fields' => [ChangeForm::ACTION => ChangeAction::Remove->value, ChangeForm::WEEKDAY => (string) $day->value, ChangeForm::TIME => $time->value],
    'label' => sprintf('Togli le %s %s', $time->value, ScheduleDates::fromWeekday($day)),
]) ?>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
  <?= $view->partial('partials/admin/hours-add', [
      'csrf' => $csrf,
      'action' => AdminUrls::SCHEDULE,
      'fields' => [ChangeForm::ACTION => ChangeAction::Add->value, ChangeForm::WEEKDAY => (string) $day->value],
      'id' => $timeId,
      'value' => $value,
      'error' => $errors->get($timeId),
      'for' => ScheduleDates::toWeekday($day),
  ]) ?>
</section>
