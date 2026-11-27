<?php

declare(strict_types=1);

use FourStreet\Admin\Http\ChangeReport;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Admin\Http\ScheduleNotice;
use FourStreet\Booking\RosterEntry;
use FourStreet\View\Template;

/**
 * L'avviso dopo una modifica dell'orario (fs-alert): che cosa è cambiato e, se ha disdetto delle prenotazioni, quante,
 * chi ha ricevuto l'email della palestra e, per nome, chi va avvisato a mano: le persone senza account (nome, giorno,
 * orario) e i clienti a cui l'email non è partita (anche l'email). Sono dati personali: li vede solo il titolare,
 * e la pagina li legge dal database (nell'indirizzo ci sono solo numeri). Una conferma si annuncia con calma
 * (role="status").
 *
 * @var Template $view
 * @var ChangeReport $report
 * @var ScheduleDates $days
 */

$notice = $report->notice;
$tone = $notice->isInformation() ? 'info' : 'success';
$when = static fn(RosterEntry $entry): string => $days->day($entry->booking->date) . ', ' . $entry->booking->time->value;
$emailed = $report->emailed();
$summary = null;
if ($report->cancelled > 0) {
    $summary = ($report->cancelled === 1 ? 'Una prenotazione disdetta.' : sprintf('%d prenotazioni disdette.', $report->cancelled))
        . match (true) {
            // Con un elenco arrivato al massimo dei numeri non si sa a chi è arrivata l'email: meglio non dirlo.
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
    <p class="fs-changes__lead">L'email non è partita, da avvisare tu:</p>
    <ul class="fs-changes__list">
<?php foreach ($report->withoutEmail as $entry): ?>
      <li class="fs-changes__item"><span class="fs-changes__who"><?= $view->e($entry->name) ?></span> <span class="fs-changes__when"><?= $view->e(($entry->email === null ? '' : $entry->email->value . ' · ') . $when($entry)) ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($report->partial): ?>
    <p><?= $view->e(sprintf('Gli elenchi arrivano al massimo a %d persone: potrebbero essercene altre da avvisare.', ScheduleNotice::MAX_LISTED)) ?></p>
<?php endif; ?>
  </div>
</div>
