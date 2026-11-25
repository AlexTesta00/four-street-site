<?php

declare(strict_types=1);

use FourStreet\Schedule\OpeningSchedule;
use FourStreet\View\Template;

/**
 * Countdown fino all'inaugurazione. Lo script lo aggiorna ogni secondo e allo zero mostra #live.
 * data-now è l'ora del server: lo script la usa per correggere l'orologio del dispositivo.
 *
 * @var Template $view
 * @var OpeningSchedule $schedule
 * @var string $dateTag "06.12.2026"
 * @var string $dateShort "domenica 6 dicembre"
 * @var string $dateLong "Domenica 6 dicembre 2026"
 * @var string|null $time orario confermato, "15:00"
 * @var string $address
 * @var string|null $instagram
 */

// Il riflesso attraversa le cifre una dopo l'altra: ogni cella parte 80 ms dopo la precedente.
$cell = 0;
?>
<section id="countdown" class="fs-stage flex flex-1 flex-col"
         data-countdown
         data-target="<?= $view->e($schedule->countdownTargetMilliseconds()) ?>"
         data-now="<?= $view->e($schedule->nowMilliseconds()) ?>">
  <div class="mx-auto flex w-full max-w-[1440px] flex-1 flex-col md:flex-row-reverse md:items-center md:justify-between md:gap-12 md:px-[var(--gutter)] md:py-16 lg:gap-20">

    <?= $view->partial('partials/logo', [
        'figureClass' => 'aspect-[16/10] w-full md:aspect-square md:w-auto md:flex-[0_1_300px] lg:flex-[0_1_440px]',
        'imageClass' => 'md:object-center',
    ]) ?>

    <div class="flex min-w-0 flex-col gap-10 px-5 pb-10 pt-8 md:max-w-[760px] md:flex-[1_1_560px] md:gap-12 md:p-0">

      <div class="flex flex-col gap-4 md:gap-6">
        <div class="fs-anim-rise fs-step-2 flex flex-wrap items-center gap-4">
          <span class="fs-tag fs-tag--event"><?= $view->e($dateTag) ?></span>
          <span class="fs-label text-fg-subtle">Inaugurazione<span class="hidden md:inline"> · San Marino</span></span>
        </div>
        <h1 class="fs-anim-rise fs-step-3 max-w-[680px] text-balance font-display text-[40px] font-normal leading-[44px] tracking-[-0.015em] md:text-[clamp(40px,4.45vw,64px)] md:leading-[1.0625]">Apriamo <?= $view->e($dateShort) ?>.</h1>
      </div>

      <div class="fs-cd-box">
        <div class="fs-cd" role="timer" aria-label="Conto alla rovescia per l'inaugurazione">
<?php foreach ($schedule->countdown()->units() as $index => $unit): ?>
          <div class="fs-cd__unit fs-anim-rise fs-step-<?= $view->e(5 + $index) ?><?= $unit->key === 'secondi' ? ' fs-cd__unit--live' : '' ?>" data-unit="<?= $view->e($unit->key) ?>" aria-hidden="true">
            <div class="fs-cd__digits">
<?php foreach ($unit->digits() as $digit): ?>
              <span class="fs-cd__cell" style="--sheen-delay: <?= $view->e(2400 + 80 * $cell++) ?>ms"><span class="fs-cd__old"></span><span class="fs-cd__new"><?= $view->e($digit) ?></span></span>
<?php endforeach; ?>
            </div>
            <div class="fs-cd__label fs-label"><?= $view->e($unit->label) ?></div>
          </div>
<?php endforeach; ?>
        </div>
      </div>

      <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
          <a href="/calendario.php" class="fs-btn fs-btn--primary fs-anim-rise fs-step-9">
            <?= $view->icon('calendar-add') ?>
            Aggiungi al calendario
          </a>
<?php if ($instagram !== null): ?>
          <a href="<?= $view->e($instagram) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-anim-rise fs-step-10">
            Seguici su Instagram
            <?= $view->icon('arrow', 'fs-btn__arrow') ?>
          </a>
<?php endif; ?>
        </div>
        <p class="fs-anim-fade fs-step-11 max-w-[560px] text-sm leading-5 text-fg-muted"><?= $view->e($dateLong) ?><?= $time !== null ? ' · ore ' . $view->e($time) : '' ?> · <?= $view->e($address) ?></p>
      </div>

    </div>
  </div>

  <?= $view->partial('partials/lane', ['class' => 'fs-anim-wipe-right fs-step-6']) ?>
</section>
