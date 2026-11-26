<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Un paragrafo dell'email HTML, con gli stili in linea.
 *
 * @var Template $view
 * @var Html|string $text
 * @var bool $muted testo secondario, più piccolo e più spento
 */

$style = $muted
    ? 'margin:0 0 16px;color:#8e8f92;font-size:14px;line-height:20px;'
    : 'margin:0 0 16px;color:#bebfc2;font-size:16px;line-height:24px;';
?>
<p style="<?= $view->e($style) ?>font-family:Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif;"><?= $view->e($text) ?></p>
