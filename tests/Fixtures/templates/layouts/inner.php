<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Layout dentro un altro layout.
 *
 * @var Template $view
 * @var Html $content
 */

$view->layout('layouts/frame', ['title' => 'Annidato']);
?>
<section><?= $content ?></section>
