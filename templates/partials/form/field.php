<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Un campo di testo con l'etichetta, l'aiuto e l'errore. L'errore sta tra l'etichetta e il campo,
 * e il campo lo annuncia con aria-invalid e aria-describedby. Senza placeholder: l'etichetta resta sempre visibile.
 *
 * @var Template $view
 * @var string $name nome del campo, che è anche il suo id (il riepilogo degli errori ci punta)
 * @var string $label
 * @var string $type "text" oppure "email"
 * @var string $value il valore scritto, da ricompilare
 * @var string|null $error
 * @var string|null $hint
 * @var array<string, string|bool> $attributes autocomplete, inputmode, maxlength…
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
  <input class="fs-input" id="<?= $view->e($name) ?>" name="<?= $view->e($name) ?>" type="<?= $view->e($type) ?>" value="<?= $view->e($value) ?>"<?= $view->attributes([
      ...$attributes,
      'aria-invalid' => $error !== null ? 'true' : null,
      'aria-describedby' => $describedBy !== '' ? $describedBy : null,
  ]) ?>>
</div>
