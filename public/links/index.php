<?php
require dirname(__DIR__) . '/_inc/bootstrap.php';

$isLive = fs_is_live();
$opening = fs_opening();
$address = (string) fs_config('address');
$dateLong = fs_date_it($opening);
$instagram = fs_link('instagram');
$whatsapp = fs_link('whatsapp');

// Quanto manca, per la card in evidenza: giorni, oppure ore nell'ultimo giorno.
$left = fs_seconds_left();
if ($left >= 86400) {
    $leftValue = intdiv($left, 86400);
    $leftUnit = $leftValue === 1 ? 'giorno' : 'giorni';
} else {
    $leftValue = (int) ceil($left / 3600);
    $leftUnit = $leftValue === 1 ? 'ora' : 'ore';
}

$pageTitle = '4Street — Link';
$pageDescription = 'Tutti i link di 4Street, palestra a San Marino.';

require dirname(__DIR__) . '/_inc/head.php';
?>
<body>

<main class="flex flex-1 flex-col lg:justify-center">
  <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 pb-10 sm:max-w-[560px] sm:px-5 sm:pt-10 lg:max-w-[1280px] lg:flex-row lg:items-center lg:justify-center lg:gap-24 lg:px-[var(--gutter)] lg:py-[72px]">

    <div class="flex min-w-0 flex-col gap-6 lg:max-w-full lg:flex-[0_1_480px]">
      <figure class="fs-logo fs-anim-wipe-up fs-step-1 aspect-[16/10] w-full lg:aspect-square">
        <img src="<?= e(fs_asset('/assets/img/logo.jpg')) ?>" alt="4Street" width="1254" height="1254" fetchpriority="high" class="fs-anim-image object-[50%_48%] lg:object-center">
      </figure>
      <div class="px-5 sm:px-0">
        <h1 class="sr-only">4Street</h1>
        <span class="fs-label fs-anim-rise fs-step-3 block text-fg-subtle">Palestra · San Marino</span>
      </div>
    </div>

    <nav aria-label="Link di 4Street" class="min-w-0 px-5 sm:px-0 lg:max-w-[520px] lg:flex-[1_1_400px]">
      <ul class="flex flex-col gap-3">

        <li class="fs-anim-rise fs-step-4">
          <a href="/" class="fs-link fs-link--featured">
            <?= fs_icon('clock', 'fs-link__icon') ?>
            <span class="fs-link__body">
              <span class="fs-title-caps">Inaugurazione</span>
              <span class="fs-link__meta"><?= e($dateLong) ?></span>
            </span>
<?php if ($isLive): ?>
            <span class="fs-tag fs-tag--event flex-none"><span class="fs-tag__dot fs-anim-breathe"></span><?= fs_event_is_over() ? 'Aperti' : 'In corso' ?></span>
<?php else: ?>
            <span class="fs-link__aside">
              <span aria-hidden="true" class="font-display text-[28px] font-medium leading-7 text-fg [font-variant-numeric:lining-nums_tabular-nums]"><?= sprintf('%02d', $leftValue) ?></span>
              <span aria-hidden="true" class="fs-micro text-accent"><?= e($leftUnit) ?></span>
              <span class="sr-only">Mancano <?= $leftValue ?> <?= e($leftUnit) ?></span>
            </span>
<?php endif; ?>
          </a>
        </li>

<?php $step = 5; ?>
<?php if ($instagram !== ''): ?>
        <li class="fs-anim-rise fs-step-<?= $step++ ?>">
          <a href="<?= e($instagram) ?>" target="_blank" rel="noopener" class="fs-link">
            <?= fs_icon('instagram', 'fs-link__icon') ?>
            <span class="fs-link__body">
              <span class="fs-title-caps">Instagram</span>
              <span class="fs-link__meta">Seguici</span>
            </span>
            <?= fs_icon('arrow', 'fs-link__arrow') ?>
          </a>
        </li>
<?php endif; ?>

<?php if ($whatsapp !== ''): ?>
        <li class="fs-anim-rise fs-step-<?= $step++ ?>">
          <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="fs-link">
            <?= fs_icon('whatsapp', 'fs-link__icon') ?>
            <span class="fs-link__body">
              <span class="fs-title-caps">WhatsApp</span>
              <span class="fs-link__meta">Scrivici</span>
            </span>
            <?= fs_icon('arrow', 'fs-link__arrow') ?>
          </a>
        </li>
<?php endif; ?>

        <li class="fs-anim-rise fs-step-<?= $step++ ?>">
          <a href="<?= e(fs_maps_url()) ?>" target="_blank" rel="noopener" class="fs-link">
            <?= fs_icon('pin', 'fs-link__icon') ?>
            <span class="fs-link__body">
              <span class="fs-title-caps">Come raggiungerci</span>
              <span class="fs-link__meta"><?= e($address) ?></span>
            </span>
            <?= fs_icon('arrow', 'fs-link__arrow') ?>
          </a>
        </li>

        <li class="fs-anim-rise fs-step-<?= $step++ ?>">
          <div class="fs-link fs-link--soon" aria-disabled="true">
            <?= fs_icon('calendar', 'fs-link__icon') ?>
            <span class="fs-link__body">
              <span class="fs-title-caps">Prenotazioni</span>
              <span class="fs-link__meta">Massimo cinque persone</span>
            </span>
            <span class="fs-tag flex-none">Presto</span>
          </div>
        </li>

        <li class="fs-anim-rise fs-step-<?= $step++ ?>">
          <div class="fs-link fs-link--soon" aria-disabled="true">
            <?= fs_icon('bag', 'fs-link__icon') ?>
            <span class="fs-link__body">
              <span class="fs-title-caps">Shop</span>
              <span class="fs-link__meta">Vendita online</span>
            </span>
            <span class="fs-tag flex-none">Presto</span>
          </div>
        </li>

      </ul>
    </nav>

  </div>
</main>

<svg class="fs-lane fs-anim-wipe-right fs-step-9" viewBox="0 0 1000 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 78 C380 78 680 70 860 40 C920 30 960 18 1000 6" vector-effect="non-scaling-stroke"></path></svg>

</body>
</html>
