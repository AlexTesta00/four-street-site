<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * @var Template $view
 * @var string $name
 */

$view->layout('layouts/frame', ['title' => 'Ciao ' . $name]);
?>
<p><?= $view->partial('partials/name', ['name' => $name]) ?></p>
<link href="<?= $view->asset('/assets/app.css') ?>">
<?= $view->icon('arrow', 'x') ?>
