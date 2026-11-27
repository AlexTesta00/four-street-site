<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\ClosureForm;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ClosuresController;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Booking\Staff\ClosureRange;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/chiusure/: le chiusure, pensate per il telefono in palestra. In cima il modulo "Chiudi la palestra": il primo
 * giorno, l'ultimo (facoltativo) e la nota per il titolare; i campi data aprono il calendario del telefono, e chi
 * scrive a mano può mettere 24/12/2026. Sotto, "In programma": le chiusure da oggi in poi, una riga ciascuna con
 * "Riapri" (partials/admin/closure-item), oppure lo stato vuoto. Ogni modulo è a sé (POST con il token CSRF; lo script
 * blocca il secondo tocco), e tutto funziona senza JavaScript.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var ClosureForm $form il modulo "Chiudi la palestra", con i valori scritti e gli errori (anche quello di una riapertura)
 * @var list<ClosureRange> $closures
 * @var ChangeReport|null $report l'avviso dopo una modifica
 * @var Alert|null $alert troppi tentativi, oppure un modulo manomesso
 * @var LocalDate $firstDay il primo giorno che si chiude (oggi, o l'inaugurazione): il calendario del telefono parte da lì
 * @var ScheduleDates $days
 */

$errors = $form->errors;
$listError = $errors->get(ClosuresController::LIST_ID);

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($errors->isEmpty() ? '' : 'Errore: ') . '4Street · Gestione · Chiusure',
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => 'Chiusure.',
    'lead' => 'Nei giorni chiusi non ci sono fasce e nessuno prenota. La nota la vedi solo tu.',
    'compact' => true,
    'nav' => $view->partial('partials/admin/nav', ['current' => 'closures', 'exact' => true]),
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $errors]) ?>
<?php if ($report !== null): ?>
<?= $view->partial('partials/admin/change-notice', ['report' => $report, 'days' => $days]) ?>
<?php endif; ?>

<section class="fs-closures__section" aria-labelledby="chiudi-titolo">
  <h2 id="chiudi-titolo" class="fs-title-caps text-fg">Chiudi la palestra</h2>
  <form method="post" action="<?= $view->e(AdminUrls::CLOSURES) ?>" class="fs-form" novalidate data-submit-once>
    <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => [ChangeForm::ACTION => ChangeAction::Close->value]]) ?>
    <?= $view->partial('partials/form/field', [
        'name' => ClosureForm::FROM,
        'label' => 'Dal giorno',
        'type' => 'date',
        'value' => ClosureForm::refill($form->from),
        'error' => $errors->get(ClosureForm::FROM),
        'hint' => null,
        'attributes' => ['required' => true, 'min' => $firstDay->value],
    ]) ?>
    <?= $view->partial('partials/form/field', [
        'name' => ClosureForm::TO,
        'label' => 'Al giorno',
        'type' => 'date',
        'value' => ClosureForm::refill($form->to),
        'error' => $errors->get(ClosureForm::TO),
        'hint' => 'Facoltativo: lascialo vuoto per chiudere un giorno solo.',
        'attributes' => ['min' => $firstDay->value],
    ]) ?>
    <?= $view->partial('partials/form/field', [
        'name' => ClosureForm::NOTE,
        'label' => 'Nota',
        'type' => 'text',
        'value' => $form->note,
        'error' => $errors->get(ClosureForm::NOTE),
        'hint' => 'Facoltativa, per esempio: ferie. La vedi solo tu, mai i clienti.',
        'attributes' => ['maxlength' => (string) ScheduleChange::NOTE_MAX_LENGTH, 'autocomplete' => 'off'],
    ]) ?>
    <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Chiudi</button>
  </form>
</section>

<section class="fs-closures__section" id="<?= $view->e(ClosuresController::LIST_ID) ?>" aria-labelledby="in-programma-titolo">
  <h2 id="in-programma-titolo" class="fs-title-caps text-fg">In programma</h2>
<?php if ($listError !== null): ?>
  <?= $view->partial('partials/admin/change-error', ['id' => ClosuresController::LIST_ID, 'error' => $listError]) ?>
<?php endif; ?>
<?php if ($closures === []): ?>
  <div class="fs-panel fs-account__empty">
    <p class="text-body text-fg-muted">Nessuna chiusura in programma.</p>
  </div>
<?php else: ?>
  <ul class="fs-closures">
<?php foreach ($closures as $closure): ?>
<?= $view->partial('partials/admin/closure-item', ['csrf' => $csrf, 'closure' => $closure, 'days' => $days]) ?>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
</section>
