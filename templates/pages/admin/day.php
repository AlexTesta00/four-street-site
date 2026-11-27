<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Booking\Staff\RosterDay;
use FourStreet\Config\SiteConfig;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /gestione/: il giorno nel pannello del titolare, pensato per il telefono in palestra. In alto il giorno prima,
 * oggi e il giorno dopo, poi la scelta rapida dei giorni (chiusa: le fasce restano nel primo schermo); sotto,
 * una scheda per fascia con chi c'è. Tutto funziona senza JavaScript.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var RosterDay $roster
 * @var Alert|null $alert
 * @var ItalianDateFormatter $dates
 */

$calendarDay = $roster->date->calendarDay();
// L'anno solo se non è quello di adesso: "Lunedì 4 gennaio 2027".
$withYear = $calendarDay->format('Y') !== $year;
$dayText = $dates->format($calendarDay, withYear: $withYear);

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
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/admin/day-nav', ['roster' => $roster, 'dates' => $dates]) ?>

<?php if ($roster->slots === []): ?>
<div class="fs-panel fs-account__empty">
  <p class="text-body text-fg-muted"><?= $view->e($roster->isBeforeOpening()
      ? sprintf('Nessuna fascia: si comincia dall\'inaugurazione, %s.', $dates->format($roster->openingDay->calendarDay(), withYear: false, capitalize: false))
      : 'Nessuna fascia questo giorno: la palestra è chiusa.') ?></p>
</div>
<?php else: ?>
<div class="fs-roster-list">
<?php foreach ($roster->slots as $slot): ?>
<?= $view->partial('partials/admin/slot', ['slot' => $slot, 'day' => $dates->format($calendarDay, withYear: $withYear, capitalize: false)]) ?>
<?php endforeach; ?>
</div>
<?php endif; ?>

<p class="fs-auth__switch">Sei nel pannello del titolare. <a class="fs-text-link" href="<?= $view->e(AccountUrls::HOME) ?>">Il tuo account</a></p>
