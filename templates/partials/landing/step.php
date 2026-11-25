<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Un passo di "Come funziona": numero, titolo e una riga di spiegazione.
 *
 * @var Template $view
 * @var string $number "01"
 * @var string $title
 * @var string $text
 */
?>
<div class="fs-step">
  <span class="fs-label text-fg-subtle tabular-nums"><?= $view->e($number) ?></span>
  <div class="flex flex-col gap-1">
    <p class="fs-title-caps text-fg"><?= $view->e($title) ?></p>
    <p class="text-body-sm text-fg-muted"><?= $view->e($text) ?></p>
  </div>
</div>
