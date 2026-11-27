<?php

declare(strict_types=1);

use FourStreet\Booking\RosterEntry;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * La conferma di una modifica dell'orario che toglie fasce con delle prenotazioni: è la risposta al POST della modifica
 * (niente redirect: la nota di una chiusura non va in un indirizzo). Il titolo chiede ("Chiudi giovedì 24 dicembre?"),
 * la frase sotto dice cosa succede con le date vere; poi quante e quali persone sono prenotate dove le fasce
 * spariscono, per giorno (orario, nome, email oppure "Senza account"), e chi riceve l'email della palestra. Il modulo
 * rimanda la stessa modifica, tutti i campi nascosti, con l'impronta di quelle prenotazioni: se nel frattempo qualcuno
 * prenota o disdice, la conferma torna con chi c'è adesso (e l'avviso che lo dice). Un bottone principale con il verbo
 * e il numero ("Chiudi e disdici 3 prenotazioni"; lo script blocca il secondo tocco) e "Annulla", che torna alla
 * pagina senza cambiare nulla.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var string $question "Chiudi giovedì 24 dicembre?"
 * @var string $lead cosa succede, con le date vere
 * @var string|null $note la nota della chiusura, a uso interno
 * @var string $button "Chiudi e disdici 3 prenotazioni"
 * @var int $count le prenotazioni da disdire
 * @var list<array{day: string, entries: list<RosterEntry>}> $groups le prenotazioni da disdire, per giorno
 * @var int $customers quante sono di clienti registrati: a loro parte l'email
 * @var int $guests quante sono di persone senza account: le avvisa il titolare
 * @var array<string, string> $fields i campi della modifica da rimandare, con l'impronta delle prenotazioni
 * @var string $action dove va il modulo
 * @var string $back la pagina della modifica, per "Annulla"
 * @var string $section la voce della navigazione: "schedule" oppure "closures"
 * @var Alert|null $alert nel frattempo è cambiato qualcosa
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Gestione · ' . rtrim($question, '?'),
    'eyebrow' => 'Gestione prenotazioni',
    'heading' => $question,
    'lead' => $lead,
    'compact' => true,
    // Una pagina della sezione (gli orari o le chiusure), non la pagina del suo link.
    'nav' => $view->partial('partials/admin/nav', ['current' => $section, 'exact' => false]),
]);

$email = match (true) {
    $guests === 0 => $customers === 1
        ? 'Gli parte l\'email della palestra, con il motivo della disdetta.'
        : 'A tutti parte l\'email della palestra, con il motivo della disdetta.',
    $customers === 0 => $guests === 1
        ? 'Non ha l\'account: nessuna email. Avvisalo tu, per esempio su WhatsApp.'
        : 'Nessuno ha l\'account: nessuna email. Avvisali tu, per esempio su WhatsApp.',
    default => 'Ai clienti registrati parte l\'email della palestra, con il motivo della disdetta. Chi non ha l\'account lo avvisi tu.',
};
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?php if ($note !== null): ?>
<p class="fs-changes__note"><span class="fs-label text-fg-subtle">Nota</span> <span><?= $view->e($note) ?></span></p>
<?php endif; ?>
<section class="fs-changes" aria-labelledby="da-disdire">
  <h2 id="da-disdire" class="fs-label text-fg-subtle"><?= $view->e($count === 1 ? 'Una prenotazione da disdire' : $count . ' prenotazioni da disdire') ?></h2>
<?php foreach ($groups as $index => $group): ?>
  <div class="fs-changes__day">
    <h3 class="fs-changes__date" id="<?= $view->e('giorno-da-disdire-' . $index) ?>"><?= $view->e($group['day']) ?></h3>
    <ul class="fs-changes__people" aria-labelledby="<?= $view->e('giorno-da-disdire-' . $index) ?>">
<?php foreach ($group['entries'] as $entry): ?>
      <li class="fs-changes__person">
        <span class="fs-changes__time"><span class="sr-only">Alle </span><?= $view->e($entry->booking->time->value) ?></span>
        <p class="fs-changes__name"><?= $view->e($entry->name) ?></p>
        <p class="fs-changes__meta"><?= $view->e($entry->email === null ? 'Senza account' : $entry->email->value) ?></p>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>
</section>
<div class="fs-auth__note">
  <?= $view->icon($customers === 0 ? 'info' : 'mail', 'fs-auth__note-icon') ?>
  <p><?= $view->e($email) ?></p>
</div>
<form method="post" action="<?= $view->e($action) ?>" class="fs-actions" data-submit-once>
  <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => $fields]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block"><?= $view->e($button) ?></button>
  <a href="<?= $view->e($back) ?>" class="fs-btn fs-btn--secondary fs-btn--block">Annulla</a>
</form>
