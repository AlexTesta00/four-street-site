<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * @var Template $view
 */

$view->layout('layouts/frame', ['title' => 'uno']);
$view->layout('layouts/frame', ['title' => 'due']);
