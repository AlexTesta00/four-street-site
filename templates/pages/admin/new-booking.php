<?php

declare(strict_types=1);

use FourStreet\Account\FullName;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\StaffBookingForm;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\Slot;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/prenotazioni/nuova/ con GET: il modulo per aggiungere una persona a una fascia. Per chi è il posto:
 * l'email di un cliente registrato (gli arriva la conferma e la trova nel suo account) oppure il nome di chi non
 * ha l'account; uno solo dei due. Un bottone principale, "Prenota il posto" (POST con il token CSRF; lo script
 * blocca il secondo tocco), e "Annulla" che torna al giorno.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var ScheduledSlot $slot
 * @var Slot $seats i posti della fascia, con quelli presi
 * @var string $day "Lunedì 7 dicembre"
 * @var StaffBookingForm $form
 * @var string $backUrl il giorno, nel pannello
 * @var Alert|null $alert troppi tentativi
 */

$seat = (int) $seats->nextSeat();
$start = $slot->startsAt->format('H:i');
$end = $slot->endsAt->format('H:i');

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($form->errors->isEmpty() ? '' : 'Errore: ') . '4Street · Gestione · Aggiungi una persona',
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => $day . ', ore ' . $start . '.',
    'lead' => null,
    'compact' => true,
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $form->errors]) ?>
<dl class="fs-dl fs-dl--stack">
  <div class="fs-dl__row">
    <dt class="fs-label">Orario</dt>
    <dd><span class="sr-only"><?= $view->e('Dalle ' . $start . ' alle ' . $end) ?></span><span aria-hidden="true"><?= $view->e($start) ?> – <?= $view->e($end) ?></span></dd>
  </div>
  <div class="fs-dl__row">
    <dt class="fs-label">Posto</dt>
    <dd class="flex flex-wrap items-center gap-x-4 gap-y-2">
      <span><?= $view->e($seat) ?> di <?= $view->e($seats->capacity) ?></span>
      <?= $view->partial('partials/booking/seats', ['slot' => $seats, 'mine' => $seat]) ?>
    </dd>
  </div>
</dl>
<form method="post" action="<?= $view->e(AdminUrls::NEW_BOOKING) ?>" class="fs-form" novalidate data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
  <input type="hidden" name="<?= $view->e(AdminUrls::DAY_PARAMETER) ?>" value="<?= $view->e($slot->date->value) ?>">
  <input type="hidden" name="<?= $view->e(AdminUrls::TIME_PARAMETER) ?>" value="<?= $view->e($slot->time->value) ?>">
  <fieldset class="fs-choice">
    <legend class="fs-choice__legend fs-label">Per chi è il posto</legend>
    <?= $view->partial('partials/form/field', [
        'name' => StaffBookingForm::EMAIL,
        'label' => 'Email del cliente registrato',
        'type' => 'email',
        'value' => $form->email,
        'error' => $form->errors->get(StaffBookingForm::EMAIL),
        'hint' => 'Gli arriva l\'email di conferma, e trova la prenotazione nel suo account.',
        'attributes' => ['autocomplete' => 'off', 'inputmode' => 'email', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'maxlength' => '254'],
    ]) ?>
    <p class="fs-choice__or">oppure</p>
    <?= $view->partial('partials/form/field', [
        'name' => StaffBookingForm::NAME,
        'label' => 'Nome, se non ha l\'account',
        'type' => 'text',
        'value' => $form->name,
        'error' => $form->errors->get(StaffBookingForm::NAME),
        'hint' => 'Chi scrive su WhatsApp o passa in palestra. Nessuna email: lo avvisi tu.',
        'attributes' => ['autocomplete' => 'off', 'autocapitalize' => 'words', 'spellcheck' => 'false', 'maxlength' => (string) FullName::MAX_LENGTH],
    ]) ?>
  </fieldset>
  <div class="fs-actions">
    <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Prenota il posto</button>
    <a href="<?= $view->e($backUrl) ?>" class="fs-btn fs-btn--secondary fs-btn--block">Annulla</a>
  </div>
</form>
