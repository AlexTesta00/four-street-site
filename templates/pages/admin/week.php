<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\WeekController;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Staff\WeekSheet;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/orari/settimana/: una settimana precisa, da lunedì a domenica, pensata per il telefono in palestra. Sotto
 * il titolo ("Dal 7 al 13 dicembre.") la settimana prima e quella dopo, poi i sette giorni, ognuno in un details
 * chiuso (partials/admin/week-day): il primo schermo mostra la settimana intera, e un giorno si apre con il dito.
 * Si apre da solo il giorno di "aperto", che arriva dopo ogni modifica e dal giorno del pannello. In fondo la copia
 * nella settimana dopo, finché quella non è cominciata (e dall'inaugurazione in poi), e il link alla settimana tipo.
 * L'avviso dopo una modifica sta nel giorno aperto, dove porta il frammento; dopo la copia, in cima. Ogni modifica è un modulo
 * a sé (POST con il token CSRF; lo script blocca il secondo tocco), e tutto funziona senza JavaScript.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var WeekSheet $sheet
 * @var string $heading "Dal 7 al 13 dicembre"
 * @var LocalDate|null $open il giorno aperto
 * @var ChangeForm $form il modulo inviato, per ricompilarlo
 * @var FormErrors $errors
 * @var ChangeReport|null $report l'avviso dopo una modifica: nel giorno aperto, oppure in cima
 * @var Alert|null $alert troppi tentativi
 * @var ItalianDateFormatter $dates
 * @var ScheduleDates $days
 */

$copyError = $errors->get(WeekController::COPY_ID);
// Si copia una settimana dall'inaugurazione in poi, in una che deve ancora cominciare: prima dell'inaugurazione le fasce
// non ci sono, e non c'è niente da copiare (né dove copiarlo: l'editor direbbe di no).
$canCopy = $sheet->canCopy && !$sheet->monday->isBefore($sheet->openingDay);

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($errors->isEmpty() ? '' : 'Errore: ') . '4Street · Gestione · ' . $heading,
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => $heading . '.',
    'lead' => 'Apri un giorno per cambiarne l\'orario: la modifica vale solo per quel giorno.',
    'compact' => true,
    // Una pagina della sezione degli orari, non la pagina del link "Orari" (la settimana tipo).
    'nav' => $view->partial('partials/admin/nav', ['current' => 'schedule', 'exact' => false]),
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $errors]) ?>
<?php if ($report !== null && $open === null): ?>
<?= $view->partial('partials/admin/change-notice', ['report' => $report, 'days' => $days]) ?>
<?php endif; ?>
<?= $view->partial('partials/admin/week-nav', ['sheet' => $sheet, 'days' => $days, 'dates' => $dates]) ?>

<div class="fs-hours fs-hours--week">
<?php foreach ($sheet->days as $day):
    $isOpen = $open !== null && $open->equals($day->date);
    ?>
<?= $view->partial('partials/admin/week-day', [
        'csrf' => $csrf,
        'day' => $day,
        'sheet' => $sheet,
        'open' => $isOpen,
        'form' => $form,
        'errors' => $errors,
        'report' => $isOpen ? $report : null,
        'days' => $days,
        'dates' => $dates,
    ]) ?>
<?php endforeach; ?>
</div>
<?php if ($canCopy || $copyError !== null): ?>

<section class="fs-hours__copy" id="<?= $view->e(WeekController::COPY_ID) ?>" aria-labelledby="copia-titolo">
  <h2 id="copia-titolo" class="fs-label text-fg-subtle">La settimana dopo</h2>
<?php if ($copyError !== null): ?>
  <?= $view->partial('partials/admin/change-error', ['id' => WeekController::COPY_ID, 'error' => $copyError]) ?>
<?php endif; ?>
<?php if ($canCopy): ?>
  <p class="fs-hours__note"><?= $view->e(sprintf(
      '%s con gli stessi orari e le stesse chiusure di questa. Se toglie fasce con delle prenotazioni, prima ti chiede conferma.',
      $days->span($sheet->monday->addDays(7), $sheet->monday->addDays(13), capitalize: true),
  )) ?></p>
  <form method="post" action="<?= $view->e(AdminUrls::week($sheet->monday)) ?>" data-submit-once>
    <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => [ChangeForm::ACTION => ChangeAction::Copy->value, ChangeForm::FROM => $sheet->monday->value]]) ?>
    <button type="submit" class="fs-btn fs-btn--secondary fs-btn--block">
      <?= $view->icon('copy') ?>
      <span>Copia questa settimana nella prossima</span>
    </button>
  </form>
<?php endif; ?>
</section>
<?php endif; ?>

<p class="fs-auth__switch">Gli orari che valgono per tutte le settimane stanno nella <a class="fs-text-link" href="<?= $view->e(AdminUrls::SCHEDULE) ?>">settimana tipo</a>.</p>
