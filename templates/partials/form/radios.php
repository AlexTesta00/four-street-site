<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Un gruppo di scelte a radio, grandi: una riga per scelta, con il nome e, se c'è, una frase che dice cosa succede
 * (fa parte dell'etichetta: tutta la riga si tocca). Per due o tre scelte che si leggono tutte insieme; per un elenco
 * lungo c'è partials/form/select. La legenda nomina il gruppo; l'aiuto e l'errore del gruppo stanno sotto la legenda,
 * e il fieldset li annuncia con aria-describedby. La prima scelta ha per id il nome del campo, così il riepilogo
 * degli errori ci porta; le altre nome-valore.
 *
 * @var Template $view
 * @var string $name nome del campo
 * @var string $legend
 * @var list<array{value: string, label: string, hint: string|null}> $options
 * @var string $checked il valore scelto; '' se non è scelto nessuno
 * @var string|null $error
 * @var string|null $hint
 */

$describedBy = trim(($hint !== null ? $name . '-aiuto ' : '') . ($error !== null ? $name . '-errore' : ''));
?>
<fieldset class="fs-radios"<?= $view->attributes(['aria-describedby' => $describedBy !== '' ? $describedBy : null]) ?>>
  <legend class="fs-radios__legend fs-label"><?= $view->e($legend) ?></legend>
<?php if ($hint !== null): ?>
  <p class="fs-field__hint" id="<?= $view->e($name) ?>-aiuto"><?= $view->e($hint) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
  <p class="fs-field__error" id="<?= $view->e($name) ?>-errore"><?= $view->icon('alert') ?><span><span class="sr-only">Errore: </span><?= $view->e($error) ?></span></p>
<?php endif; ?>
  <div class="fs-radios__options">
<?php foreach ($options as $index => $option):
    $id = $index === 0 ? $name : $name . '-' . $option['value'];
    ?>
    <label class="fs-radio" for="<?= $view->e($id) ?>">
      <input class="fs-radio__input" type="radio" id="<?= $view->e($id) ?>" name="<?= $view->e($name) ?>" value="<?= $view->e($option['value']) ?>"<?= $view->attributes([
          'checked' => $option['value'] === $checked,
          'aria-invalid' => $error !== null ? 'true' : null,
      ]) ?>>
      <span class="fs-radio__row">
        <span class="fs-radio__mark" aria-hidden="true"></span>
        <span class="fs-radio__text">
          <span class="fs-radio__label"><?= $view->e($option['label']) ?></span>
<?php if ($option['hint'] !== null): ?>
          <span class="fs-radio__hint"><?= $view->e($option['hint']) ?></span>
<?php endif; ?>
        </span>
      </span>
    </label>
<?php endforeach; ?>
  </div>
</fieldset>
