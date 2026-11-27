<?php

declare(strict_types=1);

use FourStreet\Booking\BookingUrls;
use FourStreet\Content\PendingContent;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * "Domande frequenti": la prima è aperta. Orari e prezzi, finché mancano, rispondono con il segnaposto;
 * con i segnaposto spenti quelle due domande non ci sono. "Come prenoto?" risponde al futuro finché
 * le prenotazioni dal sito non sono attive, poi spiega come si fa.
 *
 * @var Template $view
 * @var PendingContent $content
 * @var bool $open
 * @var bool $booking le prenotazioni dal sito sono attive
 * @var string $address
 * @var string $dateText "domenica 6 dicembre 2026"
 * @var string $when "domenica 6 dicembre 2026, ore 15:00" (l'orario solo se confermato)
 * @var string|null $whatsapp
 */

if ($booking) {
    $howTo = new Html(sprintf(
        'Scegli la fascia <a class="fs-text-link" href="%s">tra quelle dei prossimi giorni</a>, accedi (o crea l\'account) e conferma: ti arriva un\'email con i dettagli. Se non puoi venire, disdici dal tuo account e il posto torna libero.%s',
        $view->e(BookingUrls::DAY),
        $whatsapp === null ? '' : sprintf(' Preferisci scriverci? <a class="fs-text-link" href="%s" target="_blank" rel="noopener">WhatsApp</a>.', $view->e($whatsapp)),
    ));
} else {
    $soon = 'La prenotazione della fascia dal sito arriva presto.';
    $howTo = $whatsapp === null ? $soon : new Html(sprintf(
        '%s Intanto scrivici su <a class="fs-text-link" href="%s" target="_blank" rel="noopener">WhatsApp</a>.',
        $view->e($soon),
        $view->e($whatsapp),
    ));
}
$items = [
    ['Quante persone ci sono in sala?', 'Al massimo cinque per fascia oraria. Non una di più.'],
    ['Come prenoto?', $howTo],
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
