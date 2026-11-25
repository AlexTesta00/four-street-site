<?php

declare(strict_types=1);

use FourStreet\Content\PendingContent;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * "Lo spazio": il logo, cosa trovi in sala e le tre foto, oppure i loro segnaposto.
 * Con i segnaposto spenti la frase e le foto che mancano non ci sono; se mancano tutte le foto,
 * non c'è nemmeno la griglia.
 *
 * @var Template $view
 * @var PendingContent $content
 * @var string $address
 */

$photos = [];
foreach ([
    [$content->roomPhoto, '[Foto: la sala, bianco e nero]', 'La sala.'],
    [$content->entrancePhoto, '[Foto: l\'ingresso, bianco e nero]', 'L\'ingresso, in ' . $address . '.'],
    [$content->detailPhoto, '[Foto: un dettaglio della sala, bianco e nero]', 'Un dettaglio.'],
] as [$photo, $placeholder, $caption]) {
    if ($content->shows($photo)) {
        $photos[] = ['photo' => $photo, 'placeholder' => $placeholder, 'caption' => $caption];
    }
}

$room = $content->shows($content->room)
    ? new Html('Cosa trovi in sala: ' . $view->partial('partials/landing/value', [
        'value' => $content->room,
        'placeholder' => '[Discipline e attrezzatura da confermare]',
    ]) . '.')
    : null;
?>
<section id="spazio" aria-labelledby="spazio-titolo" class="scroll-mt-4">
  <div class="fs-gutter mx-auto flex w-full max-w-container flex-col gap-10 pt-20 lg:gap-16 lg:pt-32">

    <div class="fs-split fs-split--reverse items-end">
      <div class="fs-reveal fs-reveal--wipe lg:order-last" data-reveal>
        <?= $view->partial('partials/logo', ['figureClass' => 'aspect-square w-full', 'imageClass' => '', 'priority' => false]) ?>
      </div>
      <?= $view->partial('partials/landing/section-header', [
          'label' => 'Lo spazio',
          'title' => 'Una sala per cinque.',
          'titleId' => 'spazio-titolo',
          'intro' => $room,
          'class' => '',
      ]) ?>
    </div>

<?php if ($photos !== []): ?>
    <div class="fs-photos fs-reveal" data-reveal>
<?php foreach ($photos as $item): ?>
      <?= $view->partial('partials/landing/photo', $item) ?>
<?php endforeach; ?>
    </div>
<?php endif; ?>

  </div>
</section>
