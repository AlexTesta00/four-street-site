<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * L'azione secondaria: scrivere su WhatsApp. Senza il link in configurazione non c'è.
 *
 * @var Template $view
 * @var string|null $href
 * @var string $class classi in più (larghezza, comparsa)
 */
?>
<?php if ($href !== null): ?>
<a href="<?= $view->e($href) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary <?= $view->e($class) ?>">
  <?= $view->icon('whatsapp') ?>
  <span>Scrivici su WhatsApp</span>
  <?= $view->icon('arrow', 'fs-btn__arrow') ?>
</a>
<?php endif; ?>
