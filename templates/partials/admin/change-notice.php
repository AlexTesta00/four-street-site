<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\ScheduleNotice;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\RosterEntry;
use FourStreet\View\Template;

/**
 * L'avviso dopo una modifica dell'orario (fs-alert): che cosa è cambiato e, se ha disdetto delle prenotazioni, quante,
 * chi ha ricevuto l'email della palestra e, per nome, chi no: le persone senza account (nome, giorno, orario: le avvisa
 * il titolare) e i clienti a cui l'email non è partita o non ancora (anche l'email, e com'è adesso). Se qualcuno non ha
 * ricevuto l'email, porta alla pagina Disdette, dove si mandano le email rimaste e si vede chi avvisare: mai "è arrivata
 * l'email" quando non è vero. Sono dati personali: li vede solo il titolare, e la pagina li legge dal database
 * (nell'indirizzo ci sono solo numeri). Una conferma si annuncia con calma (role="status").
 *
 * @var Template $view
 * @var ChangeReport $report
 * @var ScheduleDates $days
 */

$notice = $report->notice;
$tone = $notice->isInformation() ? 'info' : 'success';
$when = static fn(RosterEntry $entry): string => $days->day($entry->booking->date) . ', ' . $entry->booking->time->value;
$state = static fn(RosterEntry $entry): string => match ($entry->booking->gymEmail) {
    GymEmail::Pending => 'ancora da inviare',
    GymEmail::Sending => 'in invio o interrotta',
    default => 'non partita',
};
$emailed = $report->emailed();
$summary = null;
if ($report->cancelled > 0) {
    $summary = ($report->cancelled === 1 ? 'Una prenotazione disdetta.' : sprintf('%d prenotazioni disdette.', $report->cancelled))
        . match (true) {
            // Con l'elenco arrivato al massimo dei numeri non si sa a chi è arrivata l'email: meglio non dirlo.
            $report->partial || $emailed === 0 => '',
            $report->withoutEmail === [] => ' Ai clienti registrati è arrivata l\'email della palestra, con il motivo.',
            default => ' Agli altri clienti registrati è arrivata l\'email della palestra, con il motivo.',
        };
}
?>
<div class="fs-alert fs-alert--<?= $view->e($tone) ?>" role="status">
  <?= $view->icon($tone === 'info' ? 'info' : 'check', 'fs-alert__icon') ?>
  <div class="fs-alert__body">
    <p class="fs-alert__title"><?= $view->e($notice->title()) ?></p>
    <p><?= $view->e($notice->message()) ?></p>
<?php if ($summary !== null): ?>
    <p><?= $view->e($summary) ?></p>
<?php endif; ?>
<?php if ($report->withoutAccount !== []): ?>
    <p class="fs-changes__lead">Senza account, da avvisare tu:</p>
    <ul class="fs-changes__list">
<?php foreach ($report->withoutAccount as $entry): ?>
      <li class="fs-changes__item"><span class="fs-changes__who"><?= $view->e($entry->name) ?></span> <span class="fs-changes__when"><?= $view->e($when($entry)) ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($report->withoutEmail !== []): ?>
    <p class="fs-changes__lead">L'email non è ancora arrivata:</p>
    <ul class="fs-changes__list">
<?php foreach ($report->withoutEmail as $entry): ?>
      <li class="fs-changes__item"><span class="fs-changes__who"><?= $view->e($entry->name) ?></span> <span class="fs-changes__when"><?= $view->e(($entry->email === null ? '' : $entry->email->value . ' · ') . $when($entry) . ' · ' . $state($entry)) ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($report->partial): ?>
    <p><?= $view->e(sprintf('L\'elenco arriva al massimo a %d persone: potrebbero essercene altre da avvisare, e le trovi tutte in Disdette.', ScheduleNotice::MAX_LISTED)) ?></p>
<?php endif; ?>
<?php if ($report->hasEmailsToSend()): ?>
    <p><?= $view->e(count($report->withoutEmail) === 1
        ? 'L\'email la mandi dalla pagina Disdette; se preferisci, avvisa tu quel cliente.'
        : 'Le email rimaste le mandi dalla pagina Disdette; se preferisci, avvisa tu quei clienti.') ?></p>
<?php elseif ($report->withoutEmail !== []): ?>
    <p><?= $view->e(count($report->withoutEmail) === 1
        ? 'Di un\'email in invio o interrotta non si sa se è arrivata: avvisa tu quel cliente.'
        : 'Delle email in invio o interrotte non si sa se sono arrivate: avvisa tu quei clienti.') ?></p>
<?php endif; ?>
<?php if ($report->withoutEmail !== [] || $report->partial): ?>
    <a href="<?= $view->e(AdminUrls::CANCELLATIONS) ?>" class="fs-btn fs-btn--secondary fs-btn--sm mt-2 self-start">Vai alle disdette</a>
<?php endif; ?>
  </div>
</div>
