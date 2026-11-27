<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Un numero nel modulo delle impostazioni: l'etichetta, l'aiuto e l'errore come in partials/form/field, poi il campo
 * stretto con l'unità accanto ("14 giorni", solo per gli occhi: l'etichetta dice già cosa si conta). È un campo
 * di testo con la tastiera dei numeri, non type="number": la rotella e le frecce non cambiano il valore per sbaglio,
 * e i limiti li controlla il server, che dice cosa correggere.
 *
 * @var Template $view
 * @var string $name nome del campo, che è anche il suo id (il riepilogo degli errori ci punta)
 * @var string $label
 * @var string $value il valore scritto, da ricompilare
 * @var string $unit "giorni"
 * @var int $maxLength le cifre al massimo
 * @var string|null $error
 * @var string|null $hint
 */

$describedBy = trim(($hint !== null ? $name . '-aiuto ' : '') . ($error !== null ? $name . '-errore' : ''));
?>
<div class="fs-field">
  <label class="fs-field__label fs-label" for="<?= $view->e($name) ?>"><?= $view->e($label) ?></label>
<?php if ($hint !== null): ?>
  <p class="fs-field__hint" id="<?= $view->e($name) ?>-aiuto"><?= $view->e($hint) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
  <p class="fs-field__error" id="<?= $view->e($name) ?>-errore"><?= $view->icon('alert') ?><span><span class="sr-only">Errore: </span><?= $view->e($error) ?></span></p>
<?php endif; ?>
  <div class="fs-rules__number">
    <input class="fs-input fs-rules__input" id="<?= $view->e($name) ?>" name="<?= $view->e($name) ?>" type="text" value="<?= $view->e($value) ?>"<?= $view->attributes([
        'inputmode' => 'numeric',
        'autocomplete' => 'off',
        'maxlength' => (string) $maxLength,
        'aria-invalid' => $error !== null ? 'true' : null,
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
    ]) ?>>
    <span class="fs-rules__unit" aria-hidden="true"><?= $view->e($unit) ?></span>
  </div>
</div>
