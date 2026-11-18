<?php
// Testata comune. Variabili attese: $pageTitle, $pageDescription.
$siteUrl = rtrim((string) fs_config('site_url'), '/');
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="theme-color" content="#0b0b0a">
<meta property="og:type" content="website">
<meta property="og:locale" content="it_IT">
<meta property="og:site_name" content="4Street">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<?php if ($siteUrl !== ''): ?>
<meta property="og:image" content="<?= e($siteUrl) ?>/assets/img/logo.jpg">
<?php endif; ?>
<link rel="icon" href="/assets/img/logo.jpg">
<link rel="apple-touch-icon" href="/assets/img/logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&amp;family=Bodoni+Moda:opsz,wght@6..96,400..900&amp;display=swap">
<link rel="stylesheet" href="<?= e(fs_asset('/assets/css/app.css')) ?>">
</head>
