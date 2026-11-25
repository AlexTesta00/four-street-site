<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * "La regola": cosa vuol dire per te che ogni fascia ha cinque posti.
 *
 * @var Template $view
 */

$consequences = [
    'In sala siete al massimo in cinque.',
    'Quando la fascia è piena, non entra nessun altro.',
    'Il numero non cambia: vale per ogni fascia.',
];
?>
<section id="regola" aria-labelledby="regola-titolo" class="scroll-mt-4">
  <div class="fs-gutter mx-auto w-full max-w-container pt-20 lg:pt-32">
    <div class="fs-split items-start">
      <?= $view->partial('partials/landing/section-header', [
          'label' => 'La regola',
          'title' => 'Cinque è il limite. Il resto è spazio.',
          'titleId' => 'regola-titolo',
          'intro' => 'Ogni fascia oraria ha cinque posti. Per te vuol dire questo.',
          'class' => '',
      ]) ?>
      <ol class="fs-rule fs-reveal" data-reveal>
<?php foreach ($consequences as $index => $consequence): ?>
        <li class="fs-rule__item">
          <span class="fs-label text-fg-subtle tabular-nums"><?= $view->e(sprintf('%02d', $index + 1)) ?></span>
          <p class="text-lead text-fg"><?= $view->e($consequence) ?></p>
        </li>
<?php endforeach; ?>
      </ol>
    </div>
  </div>
</section>
