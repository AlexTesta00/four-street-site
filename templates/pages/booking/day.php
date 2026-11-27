<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\User;
use FourStreet\Booking\DayBoard;
use FourStreet\Config\SiteConfig;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /prenota/: in alto i giorni, sotto le fasce del giorno scelto. Una fascia libera è un link alla conferma:
 * il percorso più corto, una riga da toccare e poi un bottone. Per chi ha già prenotato quel giorno,
 * o ha già il massimo di prenotazioni future, le fasce si vedono ma non si scelgono: la pagina dice perché
 * e porta all'account. A un ospite ricorda che per confermare serve l'account.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var DayBoard $board
 * @var User|null $visitor
 * @var ItalianDateFormatter $dates
 * @var ReturnPath $returnTo questa pagina, per tornarci dopo l'accesso
 */

$selected = $board->selected;
$mine = $selected?->mine;
$choosable = $visitor === null || ($mine === null && !$board->reachedMax());
$dayText = $selected === null ? '' : $dates->format($selected->date->calendarDay(), withYear: false, capitalize: false);

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Prenota la fascia',
    'eyebrow' => 'Prenota',
    'heading' => 'Scegli la fascia.',
    'lead' => sprintf('Ogni fascia ha %d posti, non uno di più. Scegli il giorno, poi l\'orario.', $board->capacity),
    'compact' => true,
]);
?>
<?php if ($board->requestedUnavailable): ?>
<?= $view->partial('partials/alert', ['alert' => Alert::info('Il giorno che hai aperto non si prenota: ecco i prossimi.')]) ?>
<?php endif; ?>
<?php if ($visitor !== null && $board->reachedMax() && $mine === null): ?>
<div class="fs-auth__note">
  <?= $view->icon('info', 'fs-auth__note-icon') ?>
  <div class="fs-auth__note-body">
    <p><?= $view->e(match (true) {
        // Il titolare può abbassare il massimo quando un cliente ne ha già di più: allora la pagina dice tutti e due i numeri.
        $board->upcoming > $board->maxUpcoming => sprintf('Hai già %d prenotazioni in arrivo, e il massimo è %d. Quando ne avrai meno di %d, puoi prenotare di nuovo.', $board->upcoming, $board->maxUpcoming, $board->maxUpcoming),
        $board->upcoming === 1 => 'Hai già una prenotazione in arrivo: è il massimo. Quando è passata, o se la disdici, puoi prenotare di nuovo.',
        default => sprintf('Hai già %d prenotazioni in arrivo: è il massimo. Quando una è passata, o se ne disdici una, puoi prenotare di nuovo.', $board->upcoming),
    }) ?></p>
    <a href="<?= $view->e(AccountUrls::HOME) ?>" class="fs-btn fs-btn--secondary fs-btn--sm">Le tue prenotazioni</a>
  </div>
</div>
<?php endif; ?>
<?php if ($board->days === []): ?>
<div class="fs-panel fs-account__empty">
  <p class="text-body text-fg-muted">Le prossime fasce non si prenotano ancora. Torna a trovarci fra qualche giorno.</p>
</div>
<?php else: ?>
<?= $view->partial('partials/booking/days', ['board' => $board, 'dates' => $dates]) ?>

<?php if ($selected !== null): ?>
<section aria-labelledby="fasce-giorno" class="fs-account__section">
  <h2 id="fasce-giorno" class="fs-title-caps text-fg"><?= $view->e(ucfirst($dayText)) ?></h2>
<?php if ($mine !== null && $selected->mineCancellable): ?>
  <div class="fs-auth__note">
    <?= $view->icon('check', 'fs-auth__note-icon') ?>
    <div class="fs-auth__note-body">
      <p>Questo giorno sei prenotato alle <?= $view->e($mine->time->value) ?>. Si prenota una fascia al giorno: per cambiarla, disdici prima questa.</p>
      <a href="<?= $view->e(AccountUrls::HOME) ?>" class="fs-btn fs-btn--secondary fs-btn--sm">Le tue prenotazioni</a>
    </div>
  </div>
<?php elseif ($mine !== null): ?>
  <div class="fs-auth__note">
    <?= $view->icon('check', 'fs-auth__note-icon') ?>
    <p>Questo giorno sei prenotato alle <?= $view->e($mine->time->value) ?>, e non si disdice più. Si prenota una fascia al giorno: scegli un altro giorno.</p>
  </div>
<?php endif; ?>
<?php if ($selected->slots === []): ?>
  <div class="fs-panel fs-account__empty">
    <p class="text-body text-fg-muted">Nessuna fascia questo giorno: scegline un altro.</p>
  </div>
<?php else: ?>
  <ul class="fs-slots" aria-label="<?= $view->e('Fasce di ' . $dayText) ?>">
<?php foreach ($selected->slots as $slot): ?>
    <?= $view->partial('partials/booking/slot-row', [
        'slot' => $slot,
        'day' => $selected->date,
        'choosable' => $choosable,
        'mySeat' => $mine !== null && $mine->time->value === $slot->time ? $mine->seat : null,
    ]) ?>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if ($visitor === null): ?>
<p class="fs-auth__switch">Per confermare serve l'account 4Street: <a class="fs-text-link" href="<?= $view->e(AccountUrls::to(AccountUrls::LOGIN, $returnTo)) ?>">accedi</a> oppure <a class="fs-text-link" href="<?= $view->e(AccountUrls::to(AccountUrls::REGISTER, $returnTo)) ?>">crealo</a>, ci vuole un minuto.</p>
<?php endif; ?>
