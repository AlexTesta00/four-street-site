<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * I campi nascosti di un modulo dell'orario: il token CSRF e i campi della modifica (azione, giorno, orario…), che
 * scrive la pagina. Valori che si possono mostrare a chiunque, tranne la nota di una chiusura, che resta nel modulo:
 * mai in un indirizzo.
 *
 * @var Template $view
 * @var string $csrf
 * @var array<string, string> $fields nome => valore
 */
?>
<?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
<?php foreach ($fields as $name => $value): ?>
<input type="hidden" name="<?= $view->e($name) ?>" value="<?= $view->e($value) ?>">
<?php endforeach; ?>
