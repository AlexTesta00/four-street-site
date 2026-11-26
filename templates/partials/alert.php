<?php

declare(strict_types=1);

use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * Un avviso in pagina: conferma, errore o informazione. Gli errori si annunciano subito
 * ai lettori di schermo (role="alert"), il resto con calma (role="status").
 *
 * @var Template $view
 * @var Alert $alert
 */

$icon = match ($alert->tone) {
    'success' => 'check',
    'error' => 'alert',
    'info' => 'info',
};
?>
<div class="fs-alert fs-alert--<?= $view->e($alert->tone) ?>" role="<?= $view->e($alert->role()) ?>">
  <?= $view->icon($icon, 'fs-alert__icon') ?>
  <div class="fs-alert__body">
<?php if ($alert->title !== null): ?>
    <p class="fs-alert__title"><?= $view->e($alert->title) ?></p>
<?php endif; ?>
    <p><?= $view->e($alert->message) ?></p>
  </div>
</div>
