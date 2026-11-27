<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Booking\Staff\TemplateSheet;
use FourStreet\Booking\Weekday;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Form\FormErrors;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/orari/: la settimana tipo, pensata per il telefono in palestra. Sotto il titolo due righe che dicono come
 * funziona (vale per i giorni senza un orario loro, da domani, ogni fascia dura tanti minuti) e il link a una settimana
 * precisa; poi sette schede, da lunedì a domenica (partials/admin/hours-day): gli orari come chip grandi, ognuno con il
 * bottone che lo toglie, e "Aggiungi un orario". Ogni modifica è un modulo a sé (POST con il token CSRF; lo script
 * blocca il secondo tocco): niente da salvare alla fine, e tutto funziona senza JavaScript.
 *
 * L'avviso dopo una modifica sta nella scheda del giorno cambiato, dove porta il frammento dell'indirizzo (#giorno-1);
 * senza il giorno, in cima. Gli errori stanno sul campo, e il riepilogo in cima porta lì.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var TemplateSheet $template la settimana tipo di adesso
 * @var ChangeForm $form il modulo inviato, per ricompilarlo
 * @var FormErrors $errors
 * @var ChangeReport|null $report l'avviso dopo una modifica
 * @var Weekday|null $open il giorno di cui parlano l'avviso e gli errori
 * @var Alert|null $alert troppi tentativi, oppure un modulo manomesso
 * @var ScheduleDates $days
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($errors->isEmpty() ? '' : 'Errore: ') . '4Street · Gestione · Settimana tipo',
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => 'Settimana tipo.',
    'lead' => 'Gli orari d\'inizio delle fasce, giorno per giorno. Valgono per tutti i giorni che non hanno un orario loro.',
    'compact' => true,
    'nav' => $view->partial('partials/admin/nav', ['current' => 'schedule', 'exact' => true]),
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $errors]) ?>
<?php if ($report !== null && $open === null): ?>
<?= $view->partial('partials/admin/change-notice', ['report' => $report, 'days' => $days]) ?>
<?php endif; ?>
<div class="fs-auth__note">
  <?= $view->icon('info', 'fs-auth__note-icon') ?>
  <p><?= $view->e(sprintf('Una modifica vale da domani, %s: oggi resta com\'è. Ogni fascia dura %d minuti.', $days->day($template->from), $template->slotMinutes)) ?></p>
</div>
<a href="<?= $view->e(AdminUrls::week()) ?>" class="fs-btn fs-btn--secondary fs-btn--block">
  <?= $view->icon('calendar') ?>
  <span>Cambia una settimana precisa</span>
</a>

<div class="fs-hours">
<?php foreach (Weekday::cases() as $day): ?>
<?= $view->partial('partials/admin/hours-day', [
    'csrf' => $csrf,
    'day' => $day,
    'times' => $template->week->on($day),
    'form' => $form,
    'errors' => $errors,
    'report' => $report !== null && $open === $day ? $report : null,
    'days' => $days,
]) ?>
<?php endforeach; ?>
</div>
