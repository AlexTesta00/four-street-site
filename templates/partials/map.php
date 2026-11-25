<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Mappa incorporata con il pin e l'indirizzo. Con $load falso l'iframe ha solo data-src:
 * la mappa si carica quando lo script la rende visibile.
 *
 * @var Template $view
 * @var string $address
 * @var string $embedUrl
 * @var bool $load carica subito la mappa
 * @var string $class classi in più (posizione nel layout, animazione)
 */
?>
<div class="fs-map <?= $view->e($class) ?>">
  <iframe class="fs-map__frame" title="Mappa: <?= $view->e($address) ?>" referrerpolicy="no-referrer-when-downgrade" tabindex="-1"
          <?= $load ? 'src' : 'data-src' ?>="<?= $view->e($embedUrl) ?>"></iframe>
  <svg class="fs-map__pin" viewBox="0 0 120 120" aria-hidden="true">
    <g class="fs-anim-pin fs-step-14">
      <circle class="fs-anim-pulse" cx="60" cy="60" r="26" fill="none" stroke="#8a6f3a" stroke-width="1.5"></circle>
      <rect x="48" y="48" width="24" height="24" transform="rotate(45 60 60)" fill="#cdaa66" stroke="#0b0b0a" stroke-width="3"></rect>
    </g>
  </svg>
  <div class="fs-map__chip fs-anim-rise fs-step-16">
    <span class="fs-label text-accent">4Street</span>
    <span class="text-sm leading-5"><?= $view->e($address) ?></span>
  </div>
</div>
