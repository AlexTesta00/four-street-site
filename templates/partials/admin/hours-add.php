<?php

declare(strict_types=1);

use FourStreet\Admin\Form\ChangeForm;
use FourStreet\View\Template;

/**
 * "Aggiungi un orario", in fondo a un giorno: il campo dell'ora e il bottone accanto, sulla stessa riga. Il campo è
 * un <input type="time">, che sul telefono apre il selettore dell'ora; il server accetta anche l'orario scritto a mano
 * ("7:30", "18.30") e dice cosa correggere (novalidate). Etichetta e bottone sono uguali in ogni giorno: il giorno lo
 * sentono i lettori di schermo, in fondo all'etichetta e nel nome del bottone. L'errore sta fra l'etichetta e il campo,
 * come in partials/form/field.
 *
 * @var Template $view
 * @var string $csrf
 * @var string $action dove va il modulo
 * @var array<string, string> $fields i campi nascosti: l'azione e il giorno
 * @var string $id l'id del campo, "orario-1": il riepilogo degli errori ci punta
 * @var string $value l'orario scritto, da ricompilare
 * @var string|null $error
 * @var string $for il giorno, per i lettori di schermo: "al lunedì", "a mercoledì 9 dicembre"
 */

$describedBy = $error !== null ? $id . '-errore' : null;
?>
<form method="post" action="<?= $view->e($action) ?>" class="fs-hours__add" novalidate data-submit-once>
  <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => $fields]) ?>
  <label class="fs-field__label fs-label" for="<?= $view->e($id) ?>">Aggiungi un orario<span class="sr-only"> <?= $view->e($for) ?></span></label>
<?php if ($error !== null): ?>
  <p class="fs-field__error" id="<?= $view->e($id) ?>-errore"><?= $view->icon('alert') ?><span><span class="sr-only">Errore: </span><?= $view->e($error) ?></span></p>
<?php endif; ?>
  <div class="fs-hours__add-row">
    <input class="fs-input fs-hours__input" id="<?= $view->e($id) ?>" name="<?= $view->e(ChangeForm::TIME) ?>" type="time" value="<?= $view->e($value) ?>"<?= $view->attributes([
        'required' => true,
        'aria-invalid' => $error !== null ? 'true' : null,
        'aria-describedby' => $describedBy,
    ]) ?>>
    <button type="submit" class="fs-btn fs-btn--secondary fs-hours__add-button" aria-label="<?= $view->e('Aggiungi l\'orario ' . $for) ?>">
      <?= $view->icon('plus') ?>
      <span>Aggiungi</span>
    </button>
  </div>
</form>
