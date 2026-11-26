<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Un campo password. Il valore non torna mai nella pagina. Con JavaScript compare il bottone
 * "Mostra" (data-password-toggle), che la rende leggibile; la regione nascosta lo dice ai lettori di schermo.
 *
 * @var Template $view
 * @var string $name nome e id del campo
 * @var string $label
 * @var string $autocomplete "current-password" (accesso) oppure "new-password" (password nuova)
 * @var string|null $error
 * @var string|null $hint
 * @var Html|null $after link sotto il campo (per esempio "Password dimenticata?")
 */

$describedBy = trim(($hint !== null ? $name . '-aiuto ' : '') . ($error !== null ? $name . '-errore' : ''));
$isNew = $autocomplete === 'new-password';
?>
<div class="fs-field" data-password-field>
  <label class="fs-field__label fs-label" for="<?= $view->e($name) ?>"><?= $view->e($label) ?></label>
<?php if ($hint !== null): ?>
  <p class="fs-field__hint" id="<?= $view->e($name) ?>-aiuto"><?= $view->e($hint) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
  <p class="fs-field__error" id="<?= $view->e($name) ?>-errore"><?= $view->icon('alert') ?><span><span class="sr-only">Errore: </span><?= $view->e($error) ?></span></p>
<?php endif; ?>
  <div class="fs-input-group">
    <input class="fs-input fs-input--with-toggle" id="<?= $view->e($name) ?>" name="<?= $view->e($name) ?>" type="password"<?= $view->attributes([
        'autocomplete' => $autocomplete,
        'required' => true,
        'minlength' => $isNew ? '8' : null,
        'maxlength' => '128',
        'passwordrules' => $isNew ? 'minlength: 8; maxlength: 128;' : null,
        'autocapitalize' => 'none',
        'spellcheck' => 'false',
        'aria-invalid' => $error !== null ? 'true' : null,
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
    ]) ?>>
    <button type="button" class="fs-input-group__toggle fs-label" aria-controls="<?= $view->e($name) ?>" aria-label="Mostra la password" data-password-toggle hidden>Mostra</button>
  </div>
  <span class="sr-only" aria-live="polite" data-password-status></span>
<?php if ($after !== null): ?>
  <?= $after ?>
<?php endif; ?>
</div>
