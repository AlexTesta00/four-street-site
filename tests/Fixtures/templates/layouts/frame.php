<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * @var Template $view
 * @var Html $content
 * @var string $title
 */
?>
<title><?= $view->e($title) ?></title>
<main><?= $content ?></main>
