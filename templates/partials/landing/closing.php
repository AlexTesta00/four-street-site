<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * L'invito finale: una riga per entrare, l'azione principale e WhatsApp.
 *
 * @var Template $view
 * @var bool $open
 * @var string $address
 * @var string $when "domenica 6 dicembre 2026, ore 15:00" (l'orario solo se confermato)
 * @var string $directions indicazioni su Google Maps
 * @var string|null $whatsapp
 */
?>
<section aria-labelledby="invito-titolo">
  <div class="fs-gutter fs-reveal mx-auto flex w-full max-w-container flex-col gap-8 py-20 lg:gap-10 lg:py-32" data-reveal>
    <div class="flex flex-col gap-4 lg:gap-6">
      <h2 id="invito-titolo" class="max-w-240 font-display text-display-md text-balance text-fg lg:text-display-lg">I posti sono cinque. Vieni a vederli.</h2>
      <p class="max-w-content text-body text-fg-muted"><?= $view->e($address) ?>. <?= $view->partial('partials/landing/state', [
          'open' => $open,
          'before' => 'Inaugurazione ' . $when . '.',
          'after' => 'Siamo aperti.',
      ]) ?></p>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
      <?= $view->partial('partials/landing/cta-visit', ['href' => $directions, 'class' => 'fs-btn--block sm:w-auto']) ?>
      <?= $view->partial('partials/landing/cta-whatsapp', ['href' => $whatsapp, 'class' => 'fs-btn--block sm:w-auto']) ?>
    </div>
  </div>
</section>
