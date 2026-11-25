<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Una voce della pagina dei link. Senza $href è una card "Presto", non cliccabile.
 *
 * @var Template $view
 * @var int $step ordine di entrata (classe fs-step-N)
 * @var string $icon
 * @var string $title
 * @var string|null $meta riga secondaria
 * @var string|null $href
 * @var bool $external si apre in una nuova scheda
 * @var bool $featured in evidenza (l'inaugurazione)
 * @var Html|null $aside elemento a destra; se manca, la freccia (o l'etichetta "Presto")
 */
?>
<li class="fs-anim-rise fs-step-<?= $view->e($step) ?>">
<?php if ($href === null): ?>
  <div class="fs-link fs-link--soon" aria-disabled="true">
<?php else: ?>
  <a href="<?= $view->e($href) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?> class="fs-link<?= $featured ? ' fs-link--featured' : '' ?>">
<?php endif; ?>
    <?= $view->icon($icon, 'fs-link__icon') ?>
    <span class="fs-link__body">
      <span class="fs-title-caps"><?= $view->e($title) ?></span>
<?php if ($meta !== null): ?>
      <span class="fs-link__meta"><?= $view->e($meta) ?></span>
<?php endif; ?>
    </span>
<?php if ($aside !== null): ?>
    <?= $aside ?>
<?php elseif ($href === null): ?>
    <span class="fs-tag flex-none">Presto</span>
<?php else: ?>
    <?= $view->icon('arrow', 'fs-link__arrow') ?>
<?php endif; ?>
<?php if ($href === null): ?>
  </div>
<?php else: ?>
  </a>
<?php endif; ?>
</li>
