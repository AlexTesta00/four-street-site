<?php

declare(strict_types=1);

use FourStreet\Content\Photo;
use FourStreet\View\Template;

/**
 * Una foto con la didascalia; finché la foto non c'è, un riquadro tratteggiato con il segnaposto.
 * Le misure della foto vanno negli attributi: la pagina non si sposta mentre arriva.
 *
 * @var Template $view
 * @var Photo|null $photo
 * @var string $placeholder "[Foto: la sala, bianco e nero]"
 * @var string $caption
 */
?>
<figure class="fs-photo">
  <div class="fs-photo__frame">
<?php if ($photo !== null): ?>
    <img src="<?= $view->asset($photo->src) ?>" alt="<?= $view->e($photo->alt) ?>" width="<?= $view->e($photo->width) ?>" height="<?= $view->e($photo->height) ?>" loading="lazy" decoding="async" class="fs-photo__image">
<?php else: ?>
    <span class="fs-photo__placeholder fs-micro normal-case"><?= $view->e($placeholder) ?></span>
<?php endif; ?>
  </div>
  <figcaption class="text-body-sm text-fg-subtle"><?= $view->e($caption) ?></figcaption>
</figure>
