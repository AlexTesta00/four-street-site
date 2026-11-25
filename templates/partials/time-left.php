<?php

declare(strict_types=1);

use FourStreet\Schedule\TimeLeft;
use FourStreet\View\Template;

/**
 * Quanto manca all'inaugurazione, in grande: "11 giorni", oppure "3 ore" nell'ultimo giorno.
 *
 * @var Template $view
 * @var TimeLeft $left
 */
?>
<span class="fs-link__aside">
  <span aria-hidden="true" class="font-display text-[28px] font-medium leading-7 text-fg [font-variant-numeric:lining-nums_tabular-nums]"><?= $view->e(sprintf('%02d', $left->value)) ?></span>
  <span aria-hidden="true" class="fs-micro text-accent"><?= $view->e($left->unit) ?></span>
  <span class="sr-only">Mancano <?= $view->e($left->value) ?> <?= $view->e($left->unit) ?></span>
</span>
