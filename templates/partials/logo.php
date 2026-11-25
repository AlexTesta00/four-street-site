<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Logo in una cornice. In cima alla pagina si scopre dal basso e si carica per primo ($priority);
 * più in basso si carica solo quando serve, e la comparsa la decide chi lo usa.
 *
 * @var Template $view
 * @var string $figureClass classi della cornice (proporzioni, dimensioni, animazione)
 * @var string $imageClass classi dell'immagine (inquadratura)
 * @var bool $priority in cima alla pagina
 */
?>
<figure class="fs-logo <?= $priority ? 'fs-anim-wipe-up fs-step-1 ' : '' ?><?= $view->e($figureClass) ?>">
  <img src="<?= $view->asset('/assets/img/logo.jpg') ?>" alt="4Street" width="1254" height="1254" <?= $priority ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"' ?> class="fs-anim-image object-[50%_48%] <?= $view->e($imageClass) ?>">
</figure>
