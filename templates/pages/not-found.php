<?php

declare(strict_types=1);

use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Pagina 404.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $dateTag "06.12.2026"
 */

$view->layout('layouts/base', [
    'title' => '4Street · Pagina non trovata',
    'description' => 'La pagina che cerchi non esiste. 4Street, palestra a San Marino.',
    'siteUrl' => $site->siteUrl,
]);
?>
<?= $view->partial('partials/site-bar', ['dateTag' => $dateTag]) ?>

<main class="flex flex-1 flex-col">
  <section class="fs-stage flex flex-1 flex-col">
    <div class="fs-gutter mx-auto flex w-full max-w-[1440px] flex-1 flex-col justify-center gap-4 py-8 lg:gap-6 lg:py-12">
      <span class="fs-tag fs-anim-rise fs-step-1 self-start">Errore 404</span>
      <h1 class="fs-anim-rise fs-step-2 font-display text-[clamp(56px,6.67vw,96px)] font-normal leading-none tracking-[-0.02em]">Pagina non trovata.</h1>
      <p class="fs-anim-rise fs-step-3 max-w-[560px] text-lg leading-7 text-fg-muted">L'indirizzo che hai aperto non esiste o non è più disponibile.</p>
      <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <a href="/" class="fs-btn fs-btn--primary fs-anim-rise fs-step-4">Torna alla home</a>
        <a href="/links/" class="fs-btn fs-btn--secondary fs-anim-rise fs-step-5">
          Tutti i link
          <?= $view->icon('arrow', 'fs-btn__arrow') ?>
        </a>
      </div>
    </div>

    <?= $view->partial('partials/lane', ['class' => 'fs-anim-wipe-right fs-step-6']) ?>
  </section>
</main>
