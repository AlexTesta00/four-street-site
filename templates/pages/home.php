<?php

declare(strict_types=1);

use FourStreet\Config\SiteConfig;
use FourStreet\Maps\MapLinks;
use FourStreet\Schedule\OpeningSchedule;
use FourStreet\View\Template;

/**
 * Home: countdown fino all'inaugurazione, poi la mappa. Lo stato "live" è già in pagina (nascosto)
 * perché allo zero lo script possa mostrarlo senza ricaricare.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var OpeningSchedule $schedule
 * @var MapLinks $maps
 * @var string $dateLong "Domenica 6 dicembre 2026"
 * @var string $dateShort "domenica 6 dicembre"
 * @var string $dateTag "06.12.2026"
 */

$isLive = $schedule->isLive();
$liveTitle = $schedule->isEventOver() ? '4Street · Siamo aperti' : '4Street · Ci siamo';

$view->layout('layouts/base', [
    'title' => $isLive ? $liveTitle : '4Street · Apriamo ' . $dateShort,
    'description' => $isLive
        ? '4Street, palestra a San Marino. Ti aspettiamo in ' . $site->address . '.'
        : '4Street, palestra a San Marino. Inaugurazione ' . $dateShort . ' in ' . $site->address . '.',
    'siteUrl' => $site->siteUrl,
]);
?>
<?= $view->partial('partials/site-bar', ['dateTag' => $dateTag]) ?>

<main class="flex flex-1 flex-col">

<?php if (!$isLive): ?>
<?= $view->partial('partials/countdown', [
    'schedule' => $schedule,
    'dateTag' => $dateTag,
    'dateShort' => $dateShort,
    'dateLong' => $dateLong,
    'time' => $site->openingTime(),
    'address' => $site->address,
    'instagram' => $site->link('instagram'),
]) ?>
<?php endif; ?>

<?= $view->partial('partials/live', [
    'schedule' => $schedule,
    'title' => $liveTitle,
    'dateLong' => $dateLong,
    'time' => $site->openingTime(),
    'address' => $site->address,
    'maps' => $maps,
]) ?>

</main>

<script src="<?= $view->asset('/assets/js/app.js') ?>" defer></script>
