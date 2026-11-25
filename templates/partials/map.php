<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * La mappa illustrata (MapPanel): strade scure, la corsia tratteggiata che scorre, il percorso oro
 * che si disegna fino al pin quando la mappa entra nello schermo, il riquadro con l'indirizzo.
 * Il disegno non è la geografia reale: lo dice sempre il tag "Mappa illustrativa".
 *
 * @var Template $view
 * @var string $address
 * @var string $class classi in più (misure, bordi, posizione nel layout)
 */
?>
<div class="fs-map fs-reveal <?= $view->e($class) ?>" data-reveal>
  <svg class="fs-map__art" viewBox="0 0 800 500" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Mappa illustrativa: il percorso arriva a <?= $view->e($address) ?>">
    <path class="fs-map__street" stroke-width="8" d="M120 -10 L250 510"></path>
    <path class="fs-map__street" stroke-width="8" d="M-10 160 L810 96"></path>
    <path class="fs-map__street" stroke-width="8" d="M610 -10 L560 510"></path>
    <path class="fs-map__street" stroke-width="8" d="M330 -10 L300 510"></path>
    <path class="fs-map__street" stroke-width="8" d="M450 510 L810 300"></path>
    <path class="fs-map__street" stroke-width="12" d="M396 309 L704 72"></path>
    <path class="fs-map__street" stroke-width="24" d="M-20 452 C190 444 368 372 470 252 C556 150 668 76 820 46"></path>
    <path class="fs-map__lane fs-anim-flow" d="M-20 452 C190 444 368 372 470 252 C556 150 668 76 820 46"></path>
    <path class="fs-map__route" pathLength="1" d="M-20 452 C190 444 368 372 470 252 L540 198"></path>
    <g class="fs-anim-pin">
      <circle class="fs-map__halo fs-anim-pulse" cx="540" cy="198" r="22"></circle>
      <rect class="fs-map__pin" x="530" y="188" width="20" height="20" transform="rotate(45 540 198)"></rect>
    </g>
  </svg>
  <span class="fs-map__flag fs-tag fs-tag--placeholder">Mappa illustrativa</span>
  <div class="fs-map__chip">
    <span class="fs-label text-accent">4Street</span>
    <span class="text-body-sm text-fg"><?= $view->e($address) ?></span>
  </div>
</div>
