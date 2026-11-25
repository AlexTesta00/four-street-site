<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Linea di corsia a tutta larghezza.
 *
 * @var Template $view
 * @var string $class classi in più (variante, animazione)
 */
?>
<svg class="fs-lane <?= $view->e($class) ?>" viewBox="0 0 1000 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 78 C380 78 680 70 860 40 C920 30 960 18 1000 6" vector-effect="non-scaling-stroke"></path></svg>
