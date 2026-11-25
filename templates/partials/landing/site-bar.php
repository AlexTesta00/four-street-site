<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Barra in alto della landing: il marchio e i link alle sezioni. Su mobile resta solo "Dove siamo";
 * da lg ci sono anche luogo e data dell'inaugurazione (o "Aperti") e le altre sezioni.
 *
 * @var Template $view
 * @var bool $open
 * @var string $dateTag "06.12.2026"
 */

$sections = ['#fasce' => 'Le fasce', '#spazio' => 'Lo spazio', '#domande' => 'Domande'];
?>
<div class="fs-anim-fade border-b border-hairline">
  <header class="fs-bar fs-gutter mx-auto max-w-container border-b-0">
    <a href="/" class="fs-bar__mark" aria-label="4Street, home">4Street</a>
    <nav aria-label="Principale" class="flex items-center gap-x-4 whitespace-nowrap xl:gap-x-7">
      <span class="fs-micro hidden text-fg-subtle [font-stretch:118%] lg:mr-2 lg:inline">San Marino · <?= $view->partial('partials/landing/state', ['open' => $open, 'before' => $dateTag, 'after' => 'Aperti']) ?></span>
<?php foreach ($sections as $href => $label): ?>
      <a href="<?= $view->e($href) ?>" class="fs-btn fs-btn--ghost fs-btn--sm hidden lg:inline-flex"><span class="fs-btn__label"><?= $view->e($label) ?></span></a>
<?php endforeach; ?>
      <a href="#dove" class="fs-btn fs-btn--ghost fs-btn--sm -mr-2"><span class="fs-btn__label">Dove siamo</span></a>
    </nav>
  </header>
</div>
