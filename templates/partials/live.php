<?php

declare(strict_types=1);

use FourStreet\Maps\MapLinks;
use FourStreet\Schedule\OpeningSchedule;
use FourStreet\View\Template;

/**
 * Stato "Ci siamo." (giorno dell'inaugurazione) e "Siamo aperti." (dal giorno dopo): mappa e indicazioni.
 * Prima dello zero la sezione è nascosta e la mappa non si carica: lo script la mostra allo scadere.
 *
 * @var Template $view
 * @var OpeningSchedule $schedule
 * @var string $title titolo della pagina in questo stato (lo script lo applica allo zero)
 * @var string $dateLong
 * @var string|null $time
 * @var string $address
 * @var MapLinks $maps
 */

$isLive = $schedule->isLive();
$over = $schedule->isEventOver();
?>
<section id="live" class="fs-stage flex flex-1 flex-col" data-title="<?= $view->e($title) ?>"<?= $isLive ? '' : ' hidden' ?>>
  <div class="fs-gutter mx-auto flex w-full max-w-[1440px] flex-1 py-8 lg:py-12">
    <div class="fs-live">

      <div class="fs-live__intro flex flex-col gap-4 lg:gap-6">
        <?= $view->partial('partials/status-tag', ['over' => $over, 'class' => 'fs-anim-rise fs-step-1 self-start']) ?>
        <h1 class="fs-anim-rise fs-step-2 font-display text-[clamp(56px,6.67vw,96px)] font-normal leading-none tracking-[-0.02em]"><?= $over ? 'Siamo aperti.' : 'Ci siamo.' ?></h1>
        <p class="fs-anim-rise fs-step-3 text-lg leading-7 text-fg-muted"><?= $over ? '' : "L'inaugurazione è iniziata. " ?>Ti aspettiamo in <?= $view->e($address) ?>.</p>
      </div>

      <?= $view->partial('partials/map', [
          'address' => $address,
          'embedUrl' => $maps->embed(),
          'load' => $isLive,
          'class' => 'fs-live__map fs-anim-fade fs-step-2',
      ]) ?>

      <div class="fs-live__actions flex flex-col gap-3 lg:flex-row lg:flex-wrap">
        <a href="<?= $view->e($maps->googleMaps()) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--primary fs-anim-rise fs-step-7">
          <?= $view->icon('navigate') ?>
          Apri in Google Maps
        </a>
        <a href="<?= $view->e($maps->appleMaps()) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-anim-rise fs-step-8">Apple Mappe</a>
        <button type="button" class="fs-btn fs-btn--ghost fs-anim-rise fs-step-9" data-copy="<?= $view->e($address) ?>">
          <?= $view->icon('copy') ?>
          <span class="fs-btn__label" data-copy-label>Copia indirizzo</span>
        </button>
      </div>

      <?= $view->partial('partials/event-details', [
          'dateLong' => $dateLong,
          'time' => $time,
          'address' => $address,
          'class' => 'fs-live__details',
      ]) ?>

    </div>
  </div>

  <?= $view->partial('partials/lane', ['class' => 'fs-lane--route fs-anim-wipe-right fs-step-9']) ?>
</section>
