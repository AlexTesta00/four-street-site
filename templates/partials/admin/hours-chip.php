<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Un orario come "chip": l'ora grande e, in una settimana precisa, sotto, quante persone sono prenotate ("3 prenotati")
 * e se la fascia è già cominciata o quel giorno non c'è; accanto, il bottone che lo toglie, un modulo POST a sé
 * (un quadrato di 48px con la ×: il più, ruotato), con il nome completo nell'aria-label perché i bottoni sono tanti
 * e uguali ("Togli le 18:30 dal lunedì"). Togliere un orario con delle prenotazioni passa prima dalla pagina di
 * conferma. Un orario già cominciato non si toglie più; uno che quel giorno non c'è (prima dell'inaugurazione,
 * nell'ora che il cambio dell'ora salta) è spento. Quello che si vede è per gli occhi: i lettori di schermo sentono
 * una frase ("18:30, 3 prenotati, cominciata").
 *
 * @var Template $view
 * @var string $csrf
 * @var string $time "18:30"
 * @var int|null $booked le persone prenotate in quella fascia; null nella settimana tipo, che non ha prenotazioni
 * @var string|null $state perché non è un orario come gli altri: "Cominciata", "Prima dell'inaugurazione"…; null se lo è
 * @var bool $missing la fascia quel giorno non c'è: il chip è spento
 * @var string|null $action dove va il modulo che lo toglie; null se non si toglie
 * @var array<string, string> $fields i campi nascosti del modulo che lo toglie
 * @var string $label il nome del bottone che lo toglie, "Togli le 18:30 dal lunedì"
 */

$people = match (true) {
    $booked === null => null,
    $booked === 0 => 'nessuno prenotato',
    $booked === 1 => '1 prenotato',
    default => $booked . ' prenotati',
};
// Sotto l'orario, una riga ciascuno: lo stato e le persone; "nessuno prenotato" solo per i lettori di schermo
// (a colpo d'occhio conta chi c'è).
$details = array_values(array_filter([$state, $booked !== null && $booked > 0 ? $people : null], static fn(?string $part): bool => $part !== null));
$spoken = implode(', ', array_filter([$people, $state === null ? null : lcfirst($state)], static fn(?string $part): bool => $part !== null));
$classes = 'fs-hours__chip' . ($missing ? ' fs-hours__chip--missing' : ($state !== null ? ' fs-hours__chip--started' : ''));
?>
    <li class="<?= $view->e($classes) ?>">
      <span class="fs-hours__chip-text">
        <span class="fs-hours__time"><?= $view->e($time) ?><?php if ($spoken !== ''): ?><span class="sr-only"><?= $view->e(', ' . $spoken) ?></span><?php endif; ?></span>
<?php foreach ($details as $detail): ?>
        <span class="fs-hours__detail" aria-hidden="true"><?= $view->e($detail) ?></span>
<?php endforeach; ?>
      </span>
<?php if ($action !== null): ?>
      <form method="post" action="<?= $view->e($action) ?>" class="fs-hours__chip-form" data-submit-once>
        <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => $fields]) ?>
        <button type="submit" class="fs-hours__remove" aria-label="<?= $view->e($label) ?>"><?= $view->icon('plus', 'fs-hours__x') ?></button>
      </form>
<?php endif; ?>
    </li>
