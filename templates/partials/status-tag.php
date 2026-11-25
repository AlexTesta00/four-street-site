<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Etichetta dello stato: "In corso" il giorno dell'inaugurazione, "Aperti" dal giorno dopo.
 *
 * @var Template $view
 * @var bool $over l'inaugurazione è passata
 * @var string $class classi in più
 */
?>
<span class="fs-tag fs-tag--event <?= $view->e($class) ?>"><span class="fs-tag__dot fs-anim-breathe"></span><?= $over ? 'Aperti' : 'In corso' ?></span>
