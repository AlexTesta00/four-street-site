<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * L'azione principale della pagina, sempre con le stesse parole: le indicazioni per arrivare in palestra.
 * data-primary-cta: mentre un bottone così è sullo schermo, lo script nasconde la barra delle azioni.
 *
 * @var Template $view
 * @var string $href indicazioni su Google Maps
 * @var string $class classi in più (larghezza, comparsa)
 */
?>
<a href="<?= $view->e($href) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--primary <?= $view->e($class) ?>" data-primary-cta>
  <?= $view->icon('navigate') ?>
  <span>Vieni a trovarci</span>
  <?= $view->icon('arrow', 'fs-btn__arrow') ?>
</a>
