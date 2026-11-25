<?php

declare(strict_types=1);

use FourStreet\Content\PendingContent;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * "Domande frequenti": la prima è aperta. Orari e prezzi, finché mancano, rispondono con il segnaposto;
 * con i segnaposto spenti quelle due domande non ci sono.
 *
 * @var Template $view
 * @var PendingContent $content
 * @var bool $open
 * @var string $address
 * @var string $dateText "domenica 6 dicembre 2026"
 * @var string $when "domenica 6 dicembre 2026, ore 15:00" (l'orario solo se confermato)
 * @var string|null $whatsapp
 */

$booking = 'La prenotazione della fascia dal sito arriva presto.';
$items = [
    ['Quante persone ci sono in sala?', 'Al massimo cinque per fascia oraria. Non una di più.'],
    ['Come prenoto?', $whatsapp === null ? $booking : new Html(sprintf(
        '%s Intanto scrivici su <a class="fs-text-link" href="%s" target="_blank" rel="noopener">WhatsApp</a>.',
        $view->e($booking),
        $view->e($whatsapp),
    ))],
    ['Dove siete?', 'In ' . $address . '.'],
    ['Quando aprite?', $view->partial('partials/landing/state', [
        'open' => $open,
        'before' => 'Inauguriamo ' . $when . '.',
        'after' => 'Siamo aperti. Abbiamo inaugurato ' . $dateText . '.',
    ])],
];
if ($content->shows($content->hours)) {
    $items[] = ['Che orari fate?', $view->partial('partials/landing/value', ['value' => $content->hours, 'placeholder' => '[Orari da confermare]'])];
}
if ($content->shows($content->prices)) {
    $items[] = ['Quanto costa?', $view->partial('partials/landing/value', ['value' => $content->prices, 'placeholder' => '[Prezzi da confermare]'])];
}
?>
<section id="domande" aria-labelledby="domande-titolo" class="scroll-mt-4">
  <div class="fs-gutter mx-auto w-full max-w-container pt-20 lg:pt-32">
    <div class="fs-split items-start gap-8 lg:gap-[var(--gutter)]">
      <?= $view->partial('partials/landing/section-header', [
          'label' => 'Domande frequenti',
          'title' => 'Prima di venire.',
          'titleId' => 'domande-titolo',
          'intro' => null,
          'class' => '',
      ]) ?>
      <div class="fs-faq fs-reveal" data-reveal>
<?php foreach ($items as $index => [$question, $answer]): ?>
        <?= $view->partial('partials/landing/faq-item', ['question' => $question, 'answer' => $answer, 'expanded' => $index === 0]) ?>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
