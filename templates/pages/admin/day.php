<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Staff\RosterDay;
use FourStreet\Config\SiteConfig;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/: il giorno nel pannello del titolare, pensato per il telefono in palestra. In cima la navigazione del
 * pannello; poi il giorno prima, oggi e il giorno dopo, e la scelta rapida dei giorni (chiusa: le fasce restano nel
 * primo schermo); se l'orario del giorno non è quello della settimana tipo, una riga lo dice ("Chiuso: ferie", con la
 * nota del titolare, oppure "Orario cambiato per questo giorno"); sotto, una scheda per fascia con chi c'è, comprese
 * le prenotazioni rimaste in fasce che l'orario non ha più. In fondo, finché il giorno si può ancora cambiare (non è
 * passato né prima dell'inaugurazione), il link alla sua settimana dell'orario, con questo giorno aperto.
 * Tutto funziona senza JavaScript.
 *
 * La voce "Oggi" della navigazione porta a /gestione/: è la pagina corrente (aria-current="page") quando il giorno
 * mostrato è quello che /gestione/ apre da solo, con o senza ?giorno= ($isDefaultDay); per ogni altro giorno la voce
 * segna soltanto la sezione (aria-current="true").
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var RosterDay $roster
 * @var bool $isDefaultDay il giorno mostrato è quello di /gestione/ senza giorno
 * @var Alert|null $alert
 * @var ItalianDateFormatter $dates
 */

$calendarDay = $roster->date->calendarDay();
// L'anno solo se non è quello di adesso: "Lunedì 4 gennaio 2027".
$withYear = $calendarDay->format('Y') !== $year;
$dayText = $dates->format($calendarDay, withYear: $withYear);

$plan = $roster->plan;
// Un giorno chiuso (dal titolare o dal file) lo dice anche quando restano prenotazioni fuori dall'orario. La nota è
// a uso interno del titolare: qui va bene, il pannello è solo suo.
$planText = match (true) {
    $plan->isClosed() => $plan->note === null ? 'Chiuso tutto il giorno' : 'Chiuso: ' . $plan->note,
    $plan->kind === DayKind::Changed => 'Orario cambiato per questo giorno' . ($plan->note === null ? '' : ': ' . $plan->note),
    default => null,
};
// Il giorno si cambia finché non è passato, e dall'inaugurazione in poi: la sua settimana dell'orario comincia lunedì.
$changeable = !$roster->isPast() && !$roster->isBeforeOpening();
$monday = $roster->date->addDays(1 - $roster->date->weekday()->value);

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Gestione · ' . $dayText,
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => $dayText . '.',
    'lead' => match (true) {
        $roster->isToday() => 'Oggi.',
        $roster->isPast() => 'Giorno passato: si guarda soltanto.',
        default => null,
    },
    'compact' => true,
    'nav' => $view->partial('partials/admin/nav', ['current' => 'day', 'exact' => $isDefaultDay]),
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/admin/day-nav', ['roster' => $roster, 'dates' => $dates]) ?>

<?php if ($planText !== null): ?>
<p class="fs-roster-plan">
  <?= $view->icon('calendar', 'fs-roster-plan__icon') ?>
  <span><?= $view->e($planText) ?></span>
</p>
<?php endif; ?>
<?php if ($roster->slots === [] && $roster->isBeforeOpening()): ?>
<div class="fs-panel fs-account__empty">
  <p class="text-body text-fg-muted"><?= $view->e(sprintf('Nessuna fascia: si comincia dall\'inaugurazione, %s.', $dates->format($roster->openingDay->calendarDay(), withYear: false, capitalize: false))) ?></p>
</div>
<?php elseif ($roster->slots === [] && !$plan->isClosed()): ?>
<div class="fs-panel fs-account__empty">
  <p class="text-body text-fg-muted">Nessuna fascia questo giorno: la palestra è chiusa.</p>
</div>
<?php elseif ($roster->slots !== []): ?>
<div class="fs-roster-list">
<?php foreach ($roster->slots as $slot): ?>
<?= $view->partial('partials/admin/slot', ['slot' => $slot, 'day' => $dates->format($calendarDay, withYear: $withYear, capitalize: false)]) ?>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($changeable): ?>
<a href="<?= $view->e(AdminUrls::week($monday, $roster->date)) ?>" class="fs-btn fs-btn--secondary fs-btn--block">
  <?= $view->icon('calendar') ?>
  <span>Cambia l'orario di questo giorno</span>
</a>
<?php endif; ?>

<p class="fs-auth__switch">Sei nel pannello del titolare. <a class="fs-text-link" href="<?= $view->e(AccountUrls::HOME) ?>">Il tuo account</a></p>
