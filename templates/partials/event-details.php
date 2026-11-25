<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Data, orario (solo se confermato) e luogo dell'inaugurazione.
 *
 * @var Template $view
 * @var string $dateLong
 * @var string|null $time
 * @var string $address
 * @var string $class classi in più (posizione nel layout)
 */
?>
<dl class="fs-dl <?= $view->e($class) ?>">
  <div class="fs-dl__row fs-anim-rise fs-step-4">
    <dt class="fs-label">Data</dt>
    <dd><?= $view->e($dateLong) ?></dd>
  </div>
<?php if ($time !== null): ?>
  <div class="fs-dl__row fs-anim-rise fs-step-5">
    <dt class="fs-label">Orario</dt>
    <dd><?= $view->e($time) ?></dd>
  </div>
<?php endif; ?>
  <div class="fs-dl__row fs-anim-rise fs-step-6">
    <dt class="fs-label">Luogo</dt>
    <dd><?= $view->e($address) ?></dd>
  </div>
</dl>
