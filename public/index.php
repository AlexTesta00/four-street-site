<?php
require __DIR__ . '/_inc/bootstrap.php';

$isLive = fs_is_live();
$opening = fs_opening();
$address = (string) fs_config('address');
$time = fs_opening_time();
$dateLong = fs_date_it($opening);                 // Domenica 6 dicembre 2026
$dateShort = fs_date_it($opening, false, false);  // domenica 6 dicembre
$dateTag = $opening->format('d.m.Y');
$instagram = fs_link('instagram');
$eventOver = fs_event_is_over();

$left = fs_seconds_left();
$units = [
    ['giorni', 'Giorni', intdiv($left, 86400)],
    ['ore', 'Ore', intdiv($left % 86400, 3600)],
    ['minuti', 'Minuti', intdiv($left % 3600, 60)],
    ['secondi', 'Secondi', $left % 60],
];

$liveTitle = $eventOver ? '4Street · Siamo aperti' : '4Street · Ci siamo';
$pageTitle = $isLive ? $liveTitle : '4Street · Apriamo ' . $dateShort;
$pageDescription = $isLive
    ? '4Street, palestra a San Marino. Ti aspettiamo in ' . $address . '.'
    : '4Street, palestra a San Marino. Inaugurazione ' . $dateShort . ' in ' . $address . '.';

require __DIR__ . '/_inc/head.php';
?>
<body>

<header class="fs-bar fs-gutter fs-anim-fade">
  <a href="/" class="fs-bar__mark" aria-label="4Street, home">4Street</a>
  <nav aria-label="Principale" class="flex items-center gap-x-6 gap-y-2">
    <span class="fs-micro hidden text-fg-subtle [font-stretch:118%] md:inline">San Marino · <?= e($dateTag) ?></span>
    <a href="/links/" class="fs-btn fs-btn--ghost fs-btn--sm">
      <span class="fs-btn__label"><span class="md:hidden">Link</span><span class="hidden md:inline">Tutti i link</span></span>
      <?= fs_icon('arrow', 'fs-btn__arrow') ?>
    </a>
  </nav>
</header>

<main class="flex flex-1 flex-col">

<?php if (!$isLive): ?>
  <section id="countdown" class="fs-stage flex flex-1 flex-col"
           data-countdown
           data-target="<?= fs_countdown_target()->getTimestamp() * 1000 ?>"
           data-now="<?= (int) round(microtime(true) * 1000) ?>">
    <div class="mx-auto flex w-full max-w-[1440px] flex-1 flex-col md:flex-row-reverse md:items-center md:justify-between md:gap-12 md:px-[var(--gutter)] md:py-16 lg:gap-20">

      <figure class="fs-logo fs-anim-wipe-up fs-step-1 aspect-[16/10] w-full md:aspect-square md:w-auto md:flex-[0_1_300px] lg:flex-[0_1_440px]">
        <img src="<?= e(fs_asset('/assets/img/logo.jpg')) ?>" alt="4Street" width="1254" height="1254" fetchpriority="high" class="fs-anim-image object-[50%_48%] md:object-center">
      </figure>

      <div class="flex min-w-0 flex-col gap-10 px-5 pb-10 pt-8 md:max-w-[760px] md:flex-[1_1_560px] md:gap-12 md:p-0">

        <div class="flex flex-col gap-4 md:gap-6">
          <div class="fs-anim-rise fs-step-2 flex flex-wrap items-center gap-4">
            <span class="fs-tag fs-tag--event"><?= e($dateTag) ?></span>
            <span class="fs-label text-fg-subtle">Inaugurazione<span class="hidden md:inline"> · San Marino</span></span>
          </div>
          <h1 class="fs-anim-rise fs-step-3 max-w-[680px] text-balance font-display text-[40px] font-normal leading-[44px] tracking-[-0.015em] md:text-[clamp(40px,4.45vw,64px)] md:leading-[1.0625]">Apriamo <?= e($dateShort) ?>.</h1>
          <p class="fs-anim-rise fs-step-4 max-w-[560px] text-lg leading-7 text-fg-muted">Cinque persone per fascia oraria. Non una di più.</p>
        </div>

        <div class="fs-cd-box">
          <div class="fs-cd" role="timer" aria-label="Conto alla rovescia per l'inaugurazione">
<?php
$cell = 0;
foreach ($units as $index => $unit):
    [$key, $label, $value] = $unit;
    $digits = str_split(sprintf('%02d', min(99, $value)));
?>
            <div class="fs-cd__unit fs-anim-rise fs-step-<?= 5 + $index ?><?= $key === 'secondi' ? ' fs-cd__unit--live' : '' ?>" data-unit="<?= e($key) ?>" aria-hidden="true">
              <div class="fs-cd__digits">
<?php foreach ($digits as $digit): ?>
                <span class="fs-cd__cell" style="--sheen-delay: <?= 2400 + 80 * $cell++ ?>ms"><span class="fs-cd__old"></span><span class="fs-cd__new"><?= e($digit) ?></span></span>
<?php endforeach; ?>
              </div>
              <div class="fs-cd__label fs-label"><?= e($label) ?></div>
            </div>
<?php endforeach; ?>
          </div>
        </div>

        <div class="flex flex-col gap-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <a href="/calendario.php" class="fs-btn fs-btn--primary fs-anim-rise fs-step-9">
              <?= fs_icon('calendar-add') ?>
              Aggiungi al calendario
            </a>
<?php if ($instagram !== ''): ?>
            <a href="<?= e($instagram) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-anim-rise fs-step-10">
              Seguici su Instagram
              <?= fs_icon('arrow', 'fs-btn__arrow') ?>
            </a>
<?php endif; ?>
          </div>
          <p class="fs-anim-fade fs-step-11 max-w-[560px] text-sm leading-5 text-fg-muted"><?= e($dateLong) ?><?= $time !== '' ? ' · ore ' . e($time) : '' ?> · <?= e($address) ?></p>
        </div>

      </div>
    </div>

    <svg class="fs-lane fs-anim-wipe-right fs-step-6" viewBox="0 0 1000 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 78 C380 78 680 70 860 40 C920 30 960 18 1000 6" vector-effect="non-scaling-stroke"></path></svg>
  </section>
<?php endif; ?>

  <section id="live" class="fs-stage flex flex-1 flex-col" data-title="<?= e($liveTitle) ?>"<?= $isLive ? '' : ' hidden' ?>>
    <div class="fs-gutter mx-auto flex w-full max-w-[1440px] flex-1 py-8 lg:py-12">
      <div class="fs-live">

        <div class="fs-live__intro flex flex-col gap-4 lg:gap-6">
          <span class="fs-tag fs-tag--event fs-anim-rise fs-step-1 self-start">
            <span class="fs-tag__dot fs-anim-breathe"></span>
            <?= $eventOver ? 'Aperti' : 'In corso' ?>
          </span>
          <h1 class="fs-anim-rise fs-step-2 font-display text-[clamp(56px,6.67vw,96px)] font-normal leading-none tracking-[-0.02em]"><?= $eventOver ? 'Siamo aperti.' : 'Ci siamo.' ?></h1>
          <p class="fs-anim-rise fs-step-3 text-lg leading-7 text-fg-muted"><?= $eventOver ? '' : "L'inaugurazione è iniziata. " ?>Ti aspettiamo in <?= e($address) ?>.</p>
        </div>

        <div class="fs-map fs-live__map fs-anim-fade fs-step-2">
          <iframe class="fs-map__frame" title="Mappa: <?= e($address) ?>" referrerpolicy="no-referrer-when-downgrade" tabindex="-1"
                  <?= $isLive ? 'src' : 'data-src' ?>="<?= e(fs_maps_embed_url()) ?>"></iframe>
          <svg class="fs-map__pin" viewBox="0 0 120 120" aria-hidden="true">
            <g class="fs-anim-pin fs-step-14">
              <circle class="fs-anim-pulse" cx="60" cy="60" r="26" fill="none" stroke="#8a6f3a" stroke-width="1.5"></circle>
              <rect x="48" y="48" width="24" height="24" transform="rotate(45 60 60)" fill="#cdaa66" stroke="#0b0b0a" stroke-width="3"></rect>
            </g>
          </svg>
          <div class="fs-map__chip fs-anim-rise fs-step-16">
            <span class="fs-label text-accent">4Street</span>
            <span class="text-sm leading-5"><?= e($address) ?></span>
          </div>
        </div>

        <div class="fs-live__actions flex flex-col gap-3 lg:flex-row lg:flex-wrap">
          <a href="<?= e(fs_maps_url()) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--primary fs-anim-rise fs-step-7">
            <?= fs_icon('navigate') ?>
            Apri in Google Maps
          </a>
          <a href="<?= e(fs_apple_maps_url()) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-anim-rise fs-step-8">Apple Mappe</a>
          <button type="button" class="fs-btn fs-btn--ghost fs-anim-rise fs-step-9" data-copy="<?= e($address) ?>">
            <?= fs_icon('copy') ?>
            <span class="fs-btn__label" data-copy-label>Copia indirizzo</span>
          </button>
        </div>

        <dl class="fs-dl fs-live__details">
          <div class="fs-dl__row fs-anim-rise fs-step-4">
            <dt class="fs-label">Data</dt>
            <dd><?= e($dateLong) ?></dd>
          </div>
<?php if ($time !== ''): ?>
          <div class="fs-dl__row fs-anim-rise fs-step-5">
            <dt class="fs-label">Orario</dt>
            <dd><?= e($time) ?></dd>
          </div>
<?php endif; ?>
          <div class="fs-dl__row fs-anim-rise fs-step-6">
            <dt class="fs-label">Luogo</dt>
            <dd><?= e($address) ?></dd>
          </div>
        </dl>

      </div>
    </div>

    <svg class="fs-lane fs-lane--route fs-anim-wipe-right fs-step-9" viewBox="0 0 1000 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 78 C380 78 680 70 860 40 C920 30 960 18 1000 6" vector-effect="non-scaling-stroke"></path></svg>
  </section>

</main>

<script src="<?= e(fs_asset('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
