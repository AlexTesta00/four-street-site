<?php

declare(strict_types=1);

use FourStreet\Booking\SlotCatalog;
use FourStreet\View\Template;

/**
 * "Come funziona": i tre passi della prenotazione, con le fasce da scegliere.
 * La scelta funziona anche senza JavaScript (radio e CSS); lo script aggiorna solo il riepilogo
 * "La tua fascia" (data-slot-time, data-seat), letto dai lettori di schermo quando cambia.
 * Prenotare dal sito non si può ancora: il bottone è disattivato e oggi si scrive su WhatsApp.
 *
 * @var Template $view
 * @var SlotCatalog $slots
 * @var string $address
 * @var string|null $whatsapp
 */

$chosen = $slots->defaultSlot();
?>
<section id="fasce" aria-labelledby="fasce-titolo" class="scroll-mt-4">
  <div class="fs-gutter mx-auto flex w-full max-w-container flex-col gap-10 pt-20 lg:gap-16 lg:pt-32">

    <div class="fs-split items-end gap-6 lg:gap-[var(--gutter)]">
      <?= $view->partial('partials/landing/section-header', [
          'label' => 'Come funziona',
          'title' => 'Così prenoterai il tuo posto.',
          'titleId' => 'fasce-titolo',
          'intro' => null,
          'class' => '',
      ]) ?>
      <p class="fs-reveal max-w-content text-body text-fg-muted" data-reveal>Prenotare dal sito sarà possibile a breve. Le fasce vere saranno pubblicate qui.</p>
    </div>

    <div class="fs-split fs-split--reverse items-start">
      <div class="flex flex-col gap-4">
        <?= $view->partial('partials/landing/step', ['number' => '01', 'title' => 'Scegli la fascia', 'text' => 'Ogni fascia ha cinque posti.']) ?>
        <fieldset class="fs-slots" data-slot-picker>
          <legend class="fs-slots__legend">
            <span class="fs-label text-fg-subtle">Fascia</span>
            <span class="fs-tag fs-tag--placeholder">[Fasce illustrative]</span>
          </legend>
<?php foreach ($slots->slots() as $slot): ?>
          <?= $view->partial('partials/landing/slot', ['slot' => $slot, 'checked' => $slot->time === $chosen->time]) ?>
<?php endforeach; ?>
        </fieldset>
      </div>

      <div class="flex flex-col gap-8">
        <div class="fs-panel fs-reveal flex flex-col gap-6 p-6 lg:p-8" data-reveal>
          <div class="flex flex-col gap-2">
            <p class="fs-label text-fg-subtle">La tua fascia</p>
            <div class="flex flex-col gap-2" aria-live="polite" aria-atomic="true">
              <p class="font-display text-countdown-lg text-fg tabular-nums lining-nums" data-slot-time><?= $view->e($chosen->time) ?></p>
              <p class="text-body-sm text-fg-muted">Il tuo è il posto <span data-slot-seat><?= $view->e($chosen->nextSeat()) ?></span> di <?= $view->e($slots->capacity()) ?>.</p>
            </div>
          </div>
          <div class="h-px bg-hairline" aria-hidden="true"></div>
          <?= $view->partial('partials/landing/step', ['number' => '02', 'title' => 'Prenoti il posto', 'text' => 'Dal sito, presto. Oggi su WhatsApp.']) ?>
          <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
              <button type="button" class="fs-btn fs-btn--primary min-w-0 flex-1" aria-disabled="true" aria-describedby="prenota-presto">Prenota il posto</button>
              <span id="prenota-presto" class="fs-tag">Presto</span>
            </div>
            <?= $view->partial('partials/landing/cta-whatsapp', ['href' => $whatsapp, 'class' => 'fs-btn--block']) ?>
          </div>
        </div>
        <?= $view->partial('partials/landing/step', ['number' => '03', 'title' => 'Ti alleni', 'text' => 'Vieni in ' . $address . ' all\'ora scelta.']) ?>
      </div>
    </div>

  </div>
</section>
