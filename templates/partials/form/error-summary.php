<?php

declare(strict_types=1);

use FourStreet\Http\Form\FormErrors;
use FourStreet\View\Template;

/**
 * Il riepilogo degli errori in cima al modulo: ogni errore è un link al suo campo.
 * Lo script lo mette a fuoco quando la pagina si apre (data-error-summary), così chi usa
 * la tastiera o un lettore di schermo ci arriva subito.
 *
 * @var Template $view
 * @var FormErrors $errors
 */
?>
<?php if (!$errors->isEmpty()): ?>
<div class="fs-alert fs-alert--error" role="alert" tabindex="-1" aria-labelledby="riepilogo-errori" data-error-summary>
  <?= $view->icon('alert', 'fs-alert__icon') ?>
  <div class="fs-alert__body">
    <h2 id="riepilogo-errori" class="fs-alert__title">Controlla questi campi</h2>
    <ul class="fs-alert__list">
<?php foreach ($errors->all() as $field => $message): ?>
      <li><a href="#<?= $view->e($field) ?>"><?= $view->e($message) ?></a></li>
<?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>
