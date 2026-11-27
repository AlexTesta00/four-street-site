<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\SettingsForm;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/impostazioni/: le regole delle prenotazioni che il titolare cambia dal telefono. In alto l'avviso
 * (salvate, troppi tentativi) e il riepilogo degli errori; poi il modulo, un campo sotto l'altro, ognuno con l'aiuto
 * che dice che effetto ha adesso, con i valori veri: le prenotazioni dal sito (due scelte grandi; se le ha spente chi
 * gestisce il sito nel file, una nota al posto delle scelte, e il campo non si invia), i giorni in anticipo, fino a
 * quando si prenota e si disdice (un menu: si sceglie fra valori sensati), le prenotazioni future per cliente.
 * Un solo bottone, "Salva" (POST con il token CSRF; lo script blocca il secondo tocco). In fondo, le regole fisse.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var SettingsForm $form
 * @var bool $switchable le prenotazioni dal sito si accendono e si spengono da qui (booking.online del file è acceso)
 * @var list<array{value: string, label: string}> $bookingDeadlines le scelte di "fino a quando si prenota"
 * @var list<array{value: string, label: string}> $cancelDeadlines le scelte di "fino a quando si disdice"
 * @var array<string, string> $hints l'aiuto di ogni campo, per nome del campo
 * @var int $capacity i posti per fascia, fissi (config/site.php)
 * @var int $slotMinutes la durata della fascia, fissa (config/site.php)
 * @var Alert|null $alert impostazioni salvate, oppure troppi tentativi
 */

$errors = $form->errors;

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($errors->isEmpty() ? '' : 'Errore: ') . '4Street · Gestione · Impostazioni',
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => 'Impostazioni.',
    'lead' => 'Le regole per i clienti che prenotano dal sito. Tu, dal pannello, aggiungi e disdici fino all\'inizio della fascia.',
    'compact' => true,
    'nav' => $view->partial('partials/admin/nav', ['current' => 'settings', 'exact' => true]),
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $errors]) ?>
<form method="post" action="<?= $view->e(AdminUrls::SETTINGS) ?>" class="fs-form" novalidate data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
<?php if ($switchable): ?>
  <?= $view->partial('partials/form/radios', [
      'name' => SettingsForm::ONLINE,
      'legend' => 'Prenotazioni dal sito',
      'options' => [
          ['value' => SettingsForm::ON, 'label' => 'Accese', 'hint' => 'I clienti scelgono la fascia e prenotano dal sito.'],
          ['value' => SettingsForm::OFF, 'label' => 'Spente', 'hint' => 'Le pagine per prenotare dicono «Prenotazioni presto» e la home mostra le fasce di esempio. Chi ha già prenotato vede e disdice le sue prenotazioni.'],
      ],
      'checked' => $form->online,
      'error' => $errors->get(SettingsForm::ONLINE),
      'hint' => null,
  ]) ?>
<?php else: ?>
  <div class="fs-field">
    <p class="fs-field__label fs-label">Prenotazioni dal sito</p>
    <div class="fs-auth__note">
      <?= $view->icon('info', 'fs-auth__note-icon') ?>
      <p>Spente da chi gestisce il sito, in config/site.php: da qui non si accendono. Le altre regole si salvano lo stesso, e valgono quando le prenotazioni si riaccendono.</p>
    </div>
  </div>
<?php endif; ?>
  <?= $view->partial('partials/admin/settings-number', [
      'name' => SettingsForm::DAYS_AHEAD,
      'label' => 'Quanti giorni in anticipo si prenota',
      'value' => $form->daysAhead,
      'unit' => 'giorni',
      'maxLength' => 2,
      'error' => $errors->get(SettingsForm::DAYS_AHEAD),
      'hint' => $hints[SettingsForm::DAYS_AHEAD] ?? null,
  ]) ?>
  <?= $view->partial('partials/form/select', [
      'name' => SettingsForm::BOOKING_CLOSES,
      'label' => 'Fino a quando si prenota una fascia',
      'options' => $bookingDeadlines,
      'selected' => $form->bookingCloses,
      'error' => $errors->get(SettingsForm::BOOKING_CLOSES),
      'hint' => $hints[SettingsForm::BOOKING_CLOSES] ?? null,
  ]) ?>
  <?= $view->partial('partials/form/select', [
      'name' => SettingsForm::CANCEL_CLOSES,
      'label' => 'Fino a quando il cliente disdice',
      'options' => $cancelDeadlines,
      'selected' => $form->cancelCloses,
      'error' => $errors->get(SettingsForm::CANCEL_CLOSES),
      'hint' => $hints[SettingsForm::CANCEL_CLOSES] ?? null,
  ]) ?>
  <?= $view->partial('partials/admin/settings-number', [
      'name' => SettingsForm::MAX_UPCOMING,
      'label' => 'Quante prenotazioni future per cliente',
      'value' => $form->maxUpcoming,
      'unit' => 'al massimo',
      'maxLength' => 2,
      'error' => $errors->get(SettingsForm::MAX_UPCOMING),
      'hint' => $hints[SettingsForm::MAX_UPCOMING] ?? null,
  ]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Salva</button>
</form>
<p class="fs-auth__switch"><?= $view->e(sprintf('Posti per fascia (%d) e durata della fascia (%d minuti) restano fissi: li cambia chi gestisce il sito.', $capacity, $slotMinutes)) ?></p>
