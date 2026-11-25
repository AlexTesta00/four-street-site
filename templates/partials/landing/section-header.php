<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Intestazione di una sezione: occhiello, titolo e, se c'è, il paragrafo che lo apre.
 * Sale quando entra nello schermo, come il resto della sezione.
 *
 * @var Template $view
 * @var string $label occhiello, "La regola"
 * @var string $title titolo della sezione
 * @var string $titleId id del titolo, per aria-labelledby della sezione
 * @var Html|string|null $intro
 * @var string $class classi in più (posizione nel layout)
 */
?>
<div class="fs-reveal flex flex-col gap-6 <?= $view->e($class) ?>" data-reveal>
  <p class="fs-label text-fg-subtle"><?= $view->e($label) ?></p>
  <h2 id="<?= $view->e($titleId) ?>" class="font-display text-display-md text-balance text-fg"><?= $view->e($title) ?></h2>
<?php if ($intro !== null): ?>
  <p class="max-w-content text-body text-fg-muted"><?= $view->e($intro) ?></p>
<?php endif; ?>
</div>
