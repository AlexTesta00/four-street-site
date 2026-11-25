<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Barra delle azioni in fondo allo schermo, su mobile e tablet: "Vieni a trovarci" e WhatsApp.
 * È l'ultimo elemento della pagina. Senza JavaScript resta sempre lì; con JavaScript si nasconde
 * mentre un bottone oro del contenuto (data-primary-cta) è sullo schermo.
 *
 * @var Template $view
 * @var string $directions indicazioni su Google Maps
 * @var string|null $whatsapp
 */
?>
<nav aria-label="Azioni rapide" class="fs-actionbar fs-anim-rise fs-step-12" data-action-bar>
  <div class="fs-actionbar__inner">
    <a href="<?= $view->e($directions) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--primary min-w-0 flex-1">
      <?= $view->icon('navigate') ?>
      <span>Vieni a trovarci</span>
    </a>
<?php if ($whatsapp !== null): ?>
    <a href="<?= $view->e($whatsapp) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-btn--icon" aria-label="Scrivici su WhatsApp">
      <?= $view->icon('whatsapp') ?>
    </a>
<?php endif; ?>
  </div>
</nav>
