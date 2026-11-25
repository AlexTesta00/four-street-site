<?php

declare(strict_types=1);

use FourStreet\Schedule\OpeningSchedule;
use FourStreet\View\Template;

/**
 * Apertura della landing: la regola dei posti in grande, dove siamo e le due azioni.
 * Sotto, prima dell'inaugurazione, data, countdown e calendario: è il blocco con data-countdown,
 * che allo zero esce lasciando il posto a quello "Aperti" (già in pagina, nascosto).
 * data-now è l'ora del server: lo script la usa per correggere l'orologio del dispositivo.
 *
 * @var Template $view
 * @var OpeningSchedule $schedule
 * @var bool $open
 * @var int $capacity posti per fascia oraria
 * @var string $address
 * @var string $dateTag "06.12.2026"
 * @var string $dateText "domenica 6 dicembre 2026"
 * @var string $when "domenica 6 dicembre 2026, ore 15:00" (l'orario solo se confermato)
 * @var string $openTitle titolo della pagina dallo zero in poi
 * @var string $directions indicazioni su Google Maps
 * @var string|null $whatsapp
 */
?>
<section aria-labelledby="titolo">
  <div class="fs-gutter mx-auto flex w-full max-w-container flex-col gap-8 py-12 lg:gap-24 lg:pt-32 lg:pb-24">

    <div class="fs-hero__intro">
      <p class="fs-hero__eyebrow fs-label fs-anim-rise fs-step-1 text-fg-subtle">Palestra · San Marino</p>
      <div class="fs-hero__numeral fs-anim-rise fs-step-2" aria-hidden="true">
        <span class="fs-numeral fs-chrome fs-anim-sheen fs-step-20"><?= $view->e($capacity) ?></span>
      </div>
      <h1 id="titolo" class="fs-hero__title fs-anim-rise fs-step-3 max-w-content font-display text-display-md text-balance text-fg"><span class="sr-only"><?= $view->e($capacity) ?> </span>persone per fascia oraria. Non una di più.</h1>
      <div class="fs-hero__tally flex items-center gap-4 lg:gap-5" aria-hidden="true">
        <span class="fs-seats fs-seats--lg">
<?php for ($seat = 1; $seat <= $capacity; ++$seat): ?>
          <span class="fs-seat"><span class="fs-seat__fill"></span></span>
<?php endfor; ?>
        </span>
        <span class="fs-label fs-anim-rise fs-step-4 text-fg-subtle tabular-nums"><?= $view->e($capacity . '/' . $capacity) ?></span>
      </div>
      <p class="fs-hero__lead fs-anim-rise fs-step-5 max-w-content text-lead text-fg-muted"><?= $view->e($address) ?>. <?= $view->partial('partials/landing/state', [
          'open' => $open,
          'before' => 'Apriamo ' . $dateText . '.',
          'after' => 'Siamo aperti.',
      ]) ?></p>
      <div class="fs-hero__actions flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <?= $view->partial('partials/landing/cta-visit', ['href' => $directions, 'class' => 'fs-btn--block fs-anim-rise fs-step-6 sm:w-auto']) ?>
        <?= $view->partial('partials/landing/cta-whatsapp', ['href' => $whatsapp, 'class' => 'fs-btn--block fs-anim-rise fs-step-7 sm:w-auto']) ?>
      </div>
    </div>

    <div class="mt-4 lg:mt-0">
<?php if (!$open): ?>
      <div class="fs-hero__state fs-hero__state--event fs-stage" data-state="pre"
           data-countdown
           data-target="<?= $view->e($schedule->countdownTargetMilliseconds()) ?>"
           data-now="<?= $view->e($schedule->nowMilliseconds()) ?>"
           data-open-title="<?= $view->e($openTitle) ?>">
        <span class="fs-tag fs-tag--event fs-anim-rise fs-step-8"><?= $view->e($dateTag) ?></span>
        <p class="fs-anim-rise fs-step-9 text-body text-fg-muted lg:text-lead">Inaugurazione <?= $view->e($when) ?>.</p>
        <div class="fs-hero__timer">
          <?= $view->partial('partials/countdown', ['countdown' => $schedule->countdown(), 'firstStep' => 9]) ?>
        </div>
        <a href="/calendario.php" class="fs-btn fs-btn--ghost fs-anim-rise fs-step-12 -ml-2">
          <?= $view->icon('calendar') ?>
          <span class="fs-btn__label">Aggiungi al calendario</span>
        </a>
      </div>
<?php endif; ?>
      <div class="fs-hero__state" data-state="open"<?= $open ? '' : ' hidden' ?>>
        <?= $view->partial('partials/status-tag', ['over' => true, 'class' => 'fs-anim-rise fs-step-8']) ?>
        <p class="fs-anim-rise fs-step-9 max-w-content text-body text-fg-muted lg:text-lead">Siamo aperti in <?= $view->e($address) ?>. Ti aspettiamo.</p>
      </div>
    </div>

  </div>
</section>
