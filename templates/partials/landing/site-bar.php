<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Barra in alto della landing: il marchio e i link alle sezioni. Su mobile resta solo "Dove siamo";
 * da lg ci sono anche luogo e data dell'inaugurazione (o "Aperti") e le altre sezioni.
 * Se il server offre l'area clienti, da sm c'è anche il suo link: fisso, uguale per tutti (la landing
 * non legge la sessione). Su mobile lo stesso link sta nel piè di pagina. Con quel link in più,
 * luogo e data aspettano xl, altrimenti fra lg e xl la barra non ci starebbe.
 *
 * @var Template $view
 * @var bool $open
 * @var string $dateTag "06.12.2026"
 * @var string|null $accountUrl
 */

$sections = ['#fasce' => 'Le fasce', '#spazio' => 'Lo spazio', '#domande' => 'Domande'];
?>
<div class="fs-anim-fade border-b border-hairline">
  <header class="fs-bar fs-gutter mx-auto max-w-container border-b-0">
    <a href="/" class="fs-bar__mark" aria-label="4Street, home">4Street</a>
    <nav aria-label="Principale" class="flex items-center gap-x-4 whitespace-nowrap xl:gap-x-7">
      <span class="fs-micro hidden text-fg-subtle [font-stretch:118%] lg:mr-2 <?= $accountUrl === null ? 'lg:inline' : 'xl:inline' ?>">San Marino · <?= $view->partial('partials/landing/state', ['open' => $open, 'before' => $dateTag, 'after' => 'Aperti']) ?></span>
<?php foreach ($sections as $href => $label): ?>
      <a href="<?= $view->e($href) ?>" class="fs-btn fs-btn--ghost fs-btn--sm hidden lg:inline-flex"><span class="fs-btn__label"><?= $view->e($label) ?></span></a>
<?php endforeach; ?>
<?php if ($accountUrl === null): ?>
      <a href="#dove" class="fs-btn fs-btn--ghost fs-btn--sm -mr-2"><span class="fs-btn__label">Dove siamo</span></a>
<?php else: ?>
      <a href="#dove" class="fs-btn fs-btn--ghost fs-btn--sm -mr-2 sm:mr-0"><span class="fs-btn__label">Dove siamo</span></a>
      <a href="<?= $view->e($accountUrl) ?>" class="fs-btn fs-btn--ghost fs-btn--sm -mr-2 hidden sm:inline-flex">
        <?= $view->icon('user') ?>
        <span class="fs-btn__label">Area clienti</span>
      </a>
<?php endif; ?>
    </nav>
  </header>
</div>
