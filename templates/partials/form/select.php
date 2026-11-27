<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Un menu a tendina con l'etichetta, l'aiuto e l'errore, come partials/form/field: l'errore sta tra l'etichetta
 * e il campo, e il campo lo annuncia con aria-invalid e aria-describedby. Per un elenco di scelte fisse più lungo
 * di quanto si legge a colpo d'occhio (per poche scelte, partials/form/radios). La freccia è disegnata: il menu
 * ha l'aspetto dei campi del sito, e si apre con quello del telefono.
 *
 * @var Template $view
 * @var string $name nome del campo, che è anche il suo id (il riepilogo degli errori ci punta)
 * @var string $label
 * @var list<array{value: string, label: string}> $options
 * @var string $selected il valore scelto (se non è fra le scelte, il browser mostra la prima)
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
  <span class="fs-select">
    <select class="fs-input fs-select__input" id="<?= $view->e($name) ?>" name="<?= $view->e($name) ?>"<?= $view->attributes([
        'aria-invalid' => $error !== null ? 'true' : null,
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
    ]) ?>>
<?php foreach ($options as $option): ?>
      <option value="<?= $view->e($option['value']) ?>"<?= $view->attributes(['selected' => $option['value'] === $selected]) ?>><?= $view->e($option['label']) ?></option>
<?php endforeach; ?>
    </select>
    <?= $view->icon('next', 'fs-select__icon') ?>
  </span>
</div>
