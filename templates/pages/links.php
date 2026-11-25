<?php

declare(strict_types=1);

use FourStreet\Config\SiteConfig;
use FourStreet\Maps\MapLinks;
use FourStreet\Schedule\OpeningSchedule;
use FourStreet\View\Template;

/**
 * Pagina /links/ (quella della bio di Instagram): l'inaugurazione in evidenza, poi i contatti.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var OpeningSchedule $schedule
 * @var MapLinks $maps
 * @var string $dateLong "Domenica 6 dicembre 2026"
 */

$view->layout('layouts/base', [
    'title' => '4Street · Link',
    'description' => 'Tutti i link di 4Street, palestra a San Marino.',
    'siteUrl' => $site->siteUrl,
]);

// L'inaugurazione: lo stato quando è iniziata, altrimenti quanto manca.
$opening = [
    'icon' => 'clock',
    'title' => 'Inaugurazione',
    'meta' => $dateLong,
    'href' => '/',
    'external' => false,
    'featured' => true,
    'aside' => $schedule->isLive()
        ? $view->partial('partials/status-tag', ['over' => $schedule->isEventOver(), 'class' => 'flex-none'])
        : $view->partial('partials/time-left', ['left' => $schedule->countdown()->roughly()]),
];

$cards = [$opening];
$instagram = $site->link('instagram');
if ($instagram !== null) {
    $cards[] = ['icon' => 'instagram', 'title' => 'Instagram', 'meta' => 'Seguici', 'href' => $instagram, 'external' => true, 'featured' => false, 'aside' => null];
}
$whatsapp = $site->link('whatsapp');
if ($whatsapp !== null) {
    $cards[] = ['icon' => 'whatsapp', 'title' => 'WhatsApp', 'meta' => 'Scrivici', 'href' => $whatsapp, 'external' => true, 'featured' => false, 'aside' => null];
}
$cards[] = ['icon' => 'pin', 'title' => 'Come raggiungerci', 'meta' => $site->address, 'href' => $maps->googleMaps(), 'external' => true, 'featured' => false, 'aside' => null];
$cards[] = ['icon' => 'calendar', 'title' => 'Prenotazioni', 'meta' => null, 'href' => null, 'external' => false, 'featured' => false, 'aside' => null];
$cards[] = ['icon' => 'bag', 'title' => 'Shop', 'meta' => 'Vendita online', 'href' => null, 'external' => false, 'featured' => false, 'aside' => null];
?>
<main class="flex flex-1 flex-col lg:justify-center">
  <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 pb-10 sm:max-w-[560px] sm:px-5 sm:pt-10 lg:max-w-[1280px] lg:flex-row lg:items-center lg:justify-center lg:gap-24 lg:px-[var(--gutter)] lg:py-[72px]">

    <div class="flex min-w-0 flex-col gap-6 lg:max-w-full lg:flex-[0_1_480px]">
      <?= $view->partial('partials/logo', ['figureClass' => 'aspect-[16/10] w-full lg:aspect-square', 'imageClass' => 'lg:object-center']) ?>
      <div class="px-5 sm:px-0">
        <h1 class="sr-only">4Street</h1>
        <span class="fs-label fs-anim-rise fs-step-3 block text-fg-subtle">Palestra · San Marino</span>
      </div>
    </div>

    <nav aria-label="Link di 4Street" class="min-w-0 px-5 sm:px-0 lg:max-w-[520px] lg:flex-[1_1_400px]">
      <ul class="flex flex-col gap-3">
<?php foreach ($cards as $index => $card): ?>
        <?= $view->partial('partials/link-card', ['step' => 4 + $index] + $card) ?>
<?php endforeach; ?>
      </ul>
    </nav>

  </div>
</main>

<?= $view->partial('partials/lane', ['class' => 'fs-anim-wipe-right fs-step-9']) ?>
