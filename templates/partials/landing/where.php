<?php

declare(strict_types=1);

use FourStreet\Content\PendingContent;
use FourStreet\Maps\MapLinks;
use FourStreet\View\Template;

/**
 * "Dove siamo": l'indirizzo, la mappa illustrata, i dati e le tre azioni per arrivare.
 * Su mobile la mappa sta a tutta larghezza tra il titolo e i dati (la colonna di testo si scioglie
 * con display: contents e l'ordine lo danno le classi order-*); da lg il testo è a sinistra e la mappa a destra.
 *
 * @var Template $view
 * @var MapLinks $maps
 * @var PendingContent $content
 * @var bool $open
 * @var string $address
 * @var string $dateLong "Domenica 6 dicembre 2026"
 * @var string|null $time orario confermato dell'inaugurazione, "15:00"
 */
?>
<section id="dove" aria-labelledby="dove-titolo" class="scroll-mt-4">
  <div class="fs-gutter mx-auto flex w-full max-w-container flex-col gap-8 pt-20 lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-stretch lg:gap-[var(--gutter)] lg:pt-32">

    <div class="contents lg:flex lg:flex-col lg:gap-8">
      <?= $view->partial('partials/landing/section-header', [
          'label' => 'Dove siamo',
          'title' => $address . '.',
          'titleId' => 'dove-titolo',
          'intro' => 'Ti aspettiamo qui. Apri il percorso e vieni a vedere la sala.',
          'class' => 'order-1',
      ]) ?>

      <div class="order-3 flex flex-col gap-8">
        <dl class="fs-dl fs-dl--wide">
          <div class="fs-dl__row">
            <dt class="fs-label">Indirizzo</dt>
            <dd><?= $view->e($address) ?></dd>
          </div>
          <div class="fs-dl__row">
            <dt class="fs-label">Inaugurazione</dt>
            <dd><?= $view->e($dateLong) ?><?php if (!$open && $time !== null): ?><span data-state="pre">, ore <?= $view->e($time) ?></span><?php endif; ?></dd>
          </div>
<?php if ($content->shows($content->hours)): ?>
          <div class="fs-dl__row">
            <dt class="fs-label">Orari</dt>
            <dd><?= $view->partial('partials/landing/value', ['value' => $content->hours, 'placeholder' => '[Orari da confermare]']) ?></dd>
          </div>
<?php endif; ?>
        </dl>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
          <a href="<?= $view->e($maps->googleMaps()) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--primary fs-btn--block sm:w-auto" data-primary-cta>
            <?= $view->icon('navigate') ?>
            <span>Apri in Google Maps</span>
            <?= $view->icon('arrow', 'fs-btn__arrow') ?>
          </a>
          <a href="<?= $view->e($maps->appleMaps()) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--secondary fs-btn--block sm:w-auto">
            <span>Apple Mappe</span>
            <?= $view->icon('arrow', 'fs-btn__arrow') ?>
          </a>
          <button type="button" class="fs-btn fs-btn--ghost fs-btn--block sm:w-auto" data-copy="<?= $view->e($address) ?>" data-copy-status="copia-indirizzo-stato">
            <?= $view->icon('copy') ?>
            <span class="fs-btn__label" data-copy-label>Copia indirizzo</span>
          </button>
          <p id="copia-indirizzo-stato" class="sr-only" aria-live="polite"></p>
        </div>
      </div>
    </div>

    <?= $view->partial('partials/map', [
        'address' => $address,
        'class' => 'order-2 -mx-[var(--gutter)] aspect-square border-x-0 sm:aspect-[8/5] lg:order-none lg:mx-0 lg:aspect-auto lg:min-h-110 lg:border-x',
    ]) ?>

  </div>
</section>
