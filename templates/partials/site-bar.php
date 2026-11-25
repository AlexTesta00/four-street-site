<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Barra in alto: marchio, data dell'inaugurazione e link alla pagina /links/.
 *
 * @var Template $view
 * @var string $dateTag data in cifre, "06.12.2026"
 */
?>
<header class="fs-bar fs-gutter fs-anim-fade">
  <a href="/" class="fs-bar__mark" aria-label="4Street, home">4Street</a>
  <nav aria-label="Principale" class="flex items-center gap-x-6 gap-y-2">
    <span class="fs-micro hidden text-fg-subtle [font-stretch:118%] md:inline">San Marino · <?= $view->e($dateTag) ?></span>
    <a href="/links/" class="fs-btn fs-btn--ghost fs-btn--sm">
      <span class="fs-btn__label"><span class="md:hidden">Link</span><span class="hidden md:inline">Tutti i link</span></span>
      <?= $view->icon('arrow', 'fs-btn__arrow') ?>
    </a>
  </nav>
</header>
