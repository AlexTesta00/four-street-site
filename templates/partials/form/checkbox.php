<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Una casella da spuntare. La casella vera è invisibile ma resta raggiungibile da tastiera: il quadrato
 * che la segue ne mostra lo stato (spuntata, focus, errore), anche senza JavaScript. Tutta la riga è l'etichetta,
 * alta almeno 44px.
 *
 * @var Template $view
 * @var string $name nome e id del campo
 * @var Html|string $label il testo accanto alla casella (può contenere un link)
 * @var bool $checked
 * @var string|null $error
 */
?>
<div class="fs-field">
<?php if ($error !== null): ?>
  <p class="fs-field__error" id="<?= $view->e($name) ?>-errore"><?= $view->icon('alert') ?><span><span class="sr-only">Errore: </span><?= $view->e($error) ?></span></p>
<?php endif; ?>
  <label class="fs-check" for="<?= $view->e($name) ?>">
    <input class="fs-check__input" id="<?= $view->e($name) ?>" name="<?= $view->e($name) ?>" type="checkbox" value="1"<?= $view->attributes([
        'checked' => $checked,
        'required' => true,
        'aria-invalid' => $error !== null ? 'true' : null,
        'aria-describedby' => $error !== null ? $name . '-errore' : null,
    ]) ?>>
    <span class="fs-check__box" aria-hidden="true"></span>
    <span class="fs-check__text"><?= $view->e($label) ?></span>
  </label>
</div>
