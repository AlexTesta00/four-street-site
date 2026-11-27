<?php

declare(strict_types=1);

use FourStreet\Booking\Landing\LivePreview;
use FourStreet\Booking\Landing\SlotPreview;
use FourStreet\Booking\Landing\TimetablePreview;
use FourStreet\Config\SiteConfig;
use FourStreet\Content\PendingContent;
use FourStreet\Maps\MapLinks;
use FourStreet\Schedule\OpeningSchedule;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Home: la landing. Questo template compone soltanto le sezioni (partials/landing/).
 * Prima dell'inaugurazione la pagina ha il countdown. Allo zero lo script nasconde le parti
 * data-state="pre", mostra quelle data-state="open" (già in pagina, nascoste) e cambia il titolo,
 * senza ricaricare. Dal giorno dell'apertura (o con ?anteprima=mappa) il server scrive già lo stato "open".
 * Con le prenotazioni attive ($slots con le fasce vere, o solo gli orari) i testi parlano al presente.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var OpeningSchedule $schedule
 * @var MapLinks $maps
 * @var SlotPreview $slots
 * @var string|null $accountUrl l'area clienti, se il server la offre
 * @var PendingContent $content
 * @var Html $structuredData
 * @var string $dateLong "Domenica 6 dicembre 2026"
 * @var string $dateText "domenica 6 dicembre 2026"
 * @var string $dateShort "domenica 6 dicembre"
 * @var string $dateTag "06.12.2026"
 * @var string $year "2026"
 */

$open = $schedule->isLive();
$booking = $slots instanceof LivePreview || $slots instanceof TimetablePreview;
$time = $site->openingTime();
// L'inaugurazione dentro una frase: "domenica 6 dicembre 2026, ore 15:00" (l'orario solo se confermato).
$when = $dateText . ($time !== null ? ', ore ' . $time : '');
$openTitle = '4Street · Siamo aperti';
$directions = $maps->googleMaps();
$whatsapp = $site->link('whatsapp');

$view->layout('layouts/base', [
    'title' => $open ? $openTitle : '4Street · Apriamo ' . $dateShort,
    'description' => '4Street, palestra a San Marino. '
        . ($booking ? 'Cinque persone per fascia oraria: prenota la tua dal sito. ' : 'Cinque persone per fascia oraria, non una di più. ')
        . ($open ? 'Ti aspettiamo in ' . $site->address . '.' : 'Inaugurazione ' . $dateShort . ' in ' . $site->address . '.'),
    'siteUrl' => $site->siteUrl,
    'structuredData' => $structuredData,
]);
?>
<a href="#contenuto" class="fs-skip fs-label">Vai al contenuto</a>

<?= $view->partial('partials/landing/site-bar', ['open' => $open, 'dateTag' => $dateTag, 'accountUrl' => $accountUrl]) ?>

<main id="contenuto" class="flex flex-1 flex-col">

<?= $view->partial('partials/landing/hero', [
    'schedule' => $schedule,
    'open' => $open,
    'capacity' => $slots->capacity(),
    'address' => $site->address,
    'dateTag' => $dateTag,
    'dateText' => $dateText,
    'when' => $when,
    'openTitle' => $openTitle,
    'directions' => $directions,
    'whatsapp' => $whatsapp,
]) ?>

<div class="fs-reveal fs-reveal--lane" data-reveal>
  <?= $view->partial('partials/lane', ['class' => 'h-14 lg:h-20']) ?>
</div>

<?= $view->partial('partials/landing/rule') ?>

<?= $view->partial('partials/landing/booking', ['slots' => $slots, 'address' => $site->address, 'whatsapp' => $whatsapp, 'accountUrl' => $accountUrl]) ?>

<?= $view->partial('partials/landing/space', ['content' => $content, 'address' => $site->address]) ?>

<?= $view->partial('partials/landing/where', [
    'maps' => $maps,
    'content' => $content,
    'open' => $open,
    'address' => $site->address,
    'dateLong' => $dateLong,
    'time' => $time,
]) ?>

<?= $view->partial('partials/landing/faq', [
    'content' => $content,
    'open' => $open,
    'booking' => $booking,
    'address' => $site->address,
    'dateText' => $dateText,
    'when' => $when,
    'whatsapp' => $whatsapp,
]) ?>

<?= $view->partial('partials/landing/closing', [
    'open' => $open,
    'address' => $site->address,
    'when' => $when,
    'directions' => $directions,
    'whatsapp' => $whatsapp,
]) ?>

</main>

<?= $view->partial('partials/landing/footer', [
    'content' => $content,
    'address' => $site->address,
    'instagram' => $site->link('instagram'),
    'whatsapp' => $whatsapp,
    'accountUrl' => $accountUrl,
    'year' => $year,
]) ?>

<?= $view->partial('partials/landing/action-bar', ['directions' => $directions, 'whatsapp' => $whatsapp]) ?>

<script src="<?= $view->asset('/assets/js/app.js') ?>" defer></script>
