<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Testata del documento: meta, anteprima social, font, foglio di stile e, se ci sono, i dati strutturati.
 *
 * @var Template $view
 * @var string $title
 * @var string $description
 * @var string|null $siteUrl
 * @var Html|null $structuredData blocco <script type="application/ld+json">, se la pagina ne ha uno
 * @var bool $noindex pagina con moduli o dati personali: i motori di ricerca non la indicizzano
 */
?>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $view->e($title) ?></title>
<meta name="description" content="<?= $view->e($description) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<meta name="theme-color" content="#0b0b0a">
<meta property="og:type" content="website">
<meta property="og:locale" content="it_IT">
<meta property="og:site_name" content="4Street">
<meta property="og:title" content="<?= $view->e($title) ?>">
<meta property="og:description" content="<?= $view->e($description) ?>">
<?php if ($siteUrl !== null): ?>
<meta property="og:image" content="<?= $view->e($siteUrl) ?>/assets/img/logo.jpg">
<?php endif; ?>
<link rel="icon" href="/assets/img/logo.jpg">
<link rel="apple-touch-icon" href="/assets/img/logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&amp;family=Bodoni+Moda:opsz,wght@6..96,400..900&amp;display=swap">
<link rel="stylesheet" href="<?= $view->asset('/assets/css/app.css') ?>">
<?php if ($structuredData !== null): ?>
<?= $structuredData ?>

<?php endif; ?>
</head>
