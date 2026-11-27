<?php

declare(strict_types=1);

use FourStreet\Booking\Staff\MonthSheet;
use FourStreet\Config\SiteConfig;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Template;

/**
 * /gestione/calendario/: il mese nel pannello del titolare, pensato per il telefono. Sotto il titolo il riepilogo
 * (le prenotazioni del mese) e, se ci sono prenotazioni in fasce che l'orario non ha più, l'avviso da guardare; poi
 * il mese prima e quello dopo, e i giorni del mese divisi per settimana, uno per riga: ogni giorno porta al giorno
 * del pannello. Un elenco, non una griglia a sette colonne: a 320px le caselle sarebbero larghe 40px, troppo strette
 * per leggere persone, fasce e segni, e per il dito. Tutto funziona senza JavaScript.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var MonthSheet $sheet
 * @var string $monthName "dicembre 2026"
 * @var string $monthWord "dicembre"
 * @var string|null $previousName "novembre 2026"; null al bordo della portata del pannello
 * @var string|null $nextName "gennaio 2027"; null al bordo della portata del pannello
 * @var ItalianDateFormatter $dates
 */

$heading = ucfirst($monthName);
$booked = $sheet->booked();
$outside = $sheet->outOfSchedule();

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Gestione · ' . $heading,
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => $heading . '.',
    'lead' => match ($booked) {
        0 => 'Nessuna prenotazione nel mese.',
        1 => 'Una prenotazione nel mese.',
        default => sprintf('%d prenotazioni nel mese.', $booked),
    },
    'compact' => true,
    // Il link "Calendario" porta al mese di oggi: è proprio questa pagina solo per il mese di oggi.
    'nav' => $view->partial('partials/admin/nav', ['current' => 'calendar', 'exact' => $sheet->isCurrent()]),
]);
?>
<?php if ($outside > 0): ?>
<div class="fs-alert fs-alert--error">
  <?= $view->icon('alert', 'fs-alert__icon') ?>
  <div class="fs-alert__body">
    <p class="fs-alert__title">Da guardare</p>
    <p><?= $view->e($outside === 1
        ? 'Una prenotazione è in una fascia che l\'orario non ha più. Guardala nel giorno segnato «fuori orario»: da lì la disdici.'
        : sprintf('%d prenotazioni sono in fasce che l\'orario non ha più. Guardale nei giorni segnati «fuori orario»: da lì le disdici.', $outside)) ?></p>
  </div>
</div>
<?php endif; ?>
<?= $view->partial('partials/admin/month-nav', ['sheet' => $sheet, 'previousName' => $previousName, 'nextName' => $nextName, 'dates' => $dates]) ?>

<div class="fs-month">
<?php foreach ($sheet->weeks() as $week): ?>
<?= $view->partial('partials/admin/month-week', ['week' => $week, 'monthWord' => $monthWord, 'dates' => $dates]) ?>
<?php endforeach; ?>
</div>
