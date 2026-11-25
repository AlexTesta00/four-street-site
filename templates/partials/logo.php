<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Logo in una cornice che si scopre dal basso.
 *
 * @var Template $view
 * @var string $figureClass classi della cornice (proporzioni e dimensioni)
 * @var string $imageClass classi dell'immagine (inquadratura)
 */
?>
<figure class="fs-logo fs-anim-wipe-up fs-step-1 <?= $view->e($figureClass) ?>">
  <img src="<?= $view->asset('/assets/img/logo.jpg') ?>" alt="4Street" width="1254" height="1254" fetchpriority="high" class="fs-anim-image object-[50%_48%] <?= $view->e($imageClass) ?>">
</figure>
