<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\Staff\CancellationSheet;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/disdette/: le prenotazioni disdette dalla palestra la cui fascia deve ancora cominciare, pensata per il
 * telefono. Per giorno una scheda, come nella conferma di una modifica dell'orario: ogni persona con l'orario, il nome,
 * l'email (oppure "Senza account") e, sotto, com'è andata l'email della palestra, a parole e con l'icona (il colore non
 * è mai l'unico segnale): inviata; da avvisare tu (senza account); non partita o ancora da inviare, da mandare o da
 * avvisare tu; in invio o interrotta, da avvisare tu (non si sa se è arrivata); senza esito per le disdette di prima
 * che il sito lo registrasse. In cima, se ci sono email in attesa o non partite, quante e "Invia le email rimaste"
 * (POST con il token CSRF; lo script blocca il secondo tocco). Senza disdette, una riga che lo dice. Tutto funziona
 * senza JavaScript.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var CancellationSheet $sheet
 * @var Alert|null $alert dopo un invio, oppure troppi tentativi
 * @var ScheduleDates $days
 */

$count = $sheet->count();
$toSend = $sheet->toSend();

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Gestione · Disdette',
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => 'Disdette.',
    'lead' => 'Le prenotazioni disdette dalla palestra, finché la fascia non comincia: a chi è partita l\'email, e chi va ancora avvisato.',
    'compact' => true,
    'nav' => $view->partial('partials/admin/nav', ['current' => 'cancellations', 'exact' => true]),
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?php if ($toSend > 0): ?>
<div class="fs-auth__note">
  <?= $view->icon('mail', 'fs-auth__note-icon') ?>
  <p><?= $view->e($toSend === 1
      ? 'Un\'email non è ancora partita. Il bottone la manda adesso; oppure avvisa tu quel cliente.'
      : sprintf('%d email non sono ancora partite. Il bottone le manda adesso, poche per volta: se ne restano, premilo di nuovo. Oppure avvisa tu quei clienti.', $toSend)) ?></p>
</div>
<form method="post" action="<?= $view->e(AdminUrls::CANCELLATIONS) ?>" class="fs-actions" data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Invia le email rimaste</button>
</form>
<?php endif; ?>
<?php if ($sheet->days === []): ?>
<div class="fs-panel fs-account__empty">
  <p class="text-body text-fg-muted">Nessuna prenotazione disdetta dalla palestra nelle prossime fasce.</p>
</div>
<?php else: ?>
<section class="fs-changes" aria-labelledby="disdette-titolo">
  <h2 id="disdette-titolo" class="fs-label text-fg-subtle"><?= $view->e($count === 1 ? 'Una prenotazione disdetta' : $count . ' prenotazioni disdette') ?></h2>
<?php foreach ($sheet->days as $index => $day): ?>
  <div class="fs-changes__day">
    <h3 class="fs-changes__date" id="<?= $view->e('disdette-giorno-' . $index) ?>"><?= $view->e($days->day($day->date, capitalize: true)) ?></h3>
    <ul class="fs-changes__people" aria-labelledby="<?= $view->e('disdette-giorno-' . $index) ?>">
<?php foreach ($day->entries as $entry):
    // Com'è andata l'email della palestra: il testo, il tono (done, todo, none) e l'icona.
    [$text, $tone, $icon] = $entry->booking->isGuest()
        ? ['Da avvisare tu', 'todo', 'alert']
        : match ($entry->booking->gymEmail) {
            GymEmail::Sent => ['Email inviata', 'done', 'check'],
            GymEmail::Failed => ['Email non partita: da rimandare, o da avvisare tu', 'todo', 'alert'],
            GymEmail::Pending => ['Email ancora da inviare: da mandare, o da avvisare tu', 'todo', 'alert'],
            GymEmail::Sending => ['Email in invio o interrotta: non si sa se è arrivata, da avvisare tu', 'todo', 'alert'],
            // Una disdetta di prima che il sito registrasse l'esito: nessun esito inventato.
            null => ['Esito dell\'email non registrato', 'none', 'info'],
        };
    ?>
      <li class="fs-changes__person fs-changes__person--state">
        <span class="fs-changes__time"><span class="sr-only">Alle </span><?= $view->e($entry->booking->time->value) ?></span>
        <p class="fs-changes__name"><?= $view->e($entry->name) ?></p>
        <p class="fs-changes__meta"><?= $view->e($entry->email === null ? 'Senza account' : $entry->email->value) ?></p>
        <p class="<?= $view->e('fs-changes__state fs-changes__state--' . $tone) ?>"><?= $view->icon($icon) ?><span><?= $view->e($text) ?></span></p>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>
</section>
<?php endif; ?>
