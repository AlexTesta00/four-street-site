<?php

declare(strict_types=1);

use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\Landing\LivePreview;
use FourStreet\Booking\Landing\SlotPreview;
use FourStreet\Booking\Landing\TimetablePreview;
use FourStreet\Booking\SlotCatalog;
use FourStreet\View\Template;

/**
 * "Come funziona": i tre passi della prenotazione, con le fasce da scegliere. Tre versioni:
 * - le fasce di esempio, finché le prenotazioni dal sito non sono attive: il bottone è disattivato
 *   e oggi si scrive su WhatsApp;
 * - le fasce vere del primo giorno con un posto libero: un modulo GET verso la conferma della fascia
 *   scelta ("Prenota il posto"), che funziona anche senza JavaScript;
 * - solo gli orari, se il database non risponde: niente numeri, il link porta alla pagina delle fasce.
 * La scelta funziona senza JavaScript (radio e CSS); lo script aggiorna solo il riepilogo "La tua fascia"
 * (data-slot-time, data-seat), letto dai lettori di schermo quando cambia.
 *
 * @var Template $view
 * @var SlotPreview $slots
 * @var string $address
 * @var string|null $whatsapp
 * @var string|null $accountUrl l'area clienti, se il server la offre
 */

$live = $slots instanceof LivePreview ? $slots : null;
$timetable = $slots instanceof TimetablePreview ? $slots : null;
$catalog = $slots instanceof SlotCatalog ? $slots : null;
$online = $live !== null || $timetable !== null;
$chosen = $catalog?->defaultSlot();
$day = $live !== null ? $live->day : $timetable?->day;
$dayLabel = $live !== null ? $live->dayLabel : ($timetable !== null ? $timetable->dayLabel : '');
$form = $live !== null && $day !== null && $chosen !== null;

$intro = match (true) {
    $live !== null && $day !== null => 'Qui sotto le fasce di ' . $dayLabel . ', con i posti liberi. Gli altri giorni sono nella pagina delle prenotazioni.',
    $timetable !== null && $day !== null => 'Le fasce di ' . $dayLabel . '. I posti liberi li vedi nella pagina delle prenotazioni.',
    $online => 'Le prossime fasce non si prenotano ancora. Per il tuo posto, intanto, scrivici.',
    default => 'Prenotare dal sito sarà possibile a breve. Le fasce vere saranno pubblicate qui.',
};
?>
<section id="fasce" aria-labelledby="fasce-titolo" class="scroll-mt-4">
  <div class="fs-gutter mx-auto flex w-full max-w-container flex-col gap-10 pt-20 lg:gap-16 lg:pt-32">

    <div class="fs-split items-end gap-6 lg:gap-[var(--gutter)]">
      <?= $view->partial('partials/landing/section-header', [
          'label' => 'Come funziona',
          'title' => $online ? 'Così prenoti il tuo posto.' : 'Così prenoterai il tuo posto.',
          'titleId' => 'fasce-titolo',
          'intro' => null,
          'class' => '',
      ]) ?>
      <p class="fs-reveal max-w-content text-body text-fg-muted" data-reveal><?= $view->e($intro) ?></p>
    </div>

<?php if ($form): ?>
    <form method="get" action="<?= $view->e(BookingUrls::CONFIRM) ?>" class="fs-split fs-split--reverse items-start">
      <input type="hidden" name="<?= $view->e(BookingUrls::DAY_PARAMETER) ?>" value="<?= $view->e($day->value) ?>">
<?php else: ?>
    <div class="fs-split fs-split--reverse items-start">
<?php endif; ?>
      <div class="flex flex-col gap-4">
        <?= $view->partial('partials/landing/step', ['number' => '01', 'title' => 'Scegli la fascia', 'text' => 'Ogni fascia ha cinque posti.']) ?>
<?php if ($catalog !== null && $catalog->slots() !== []): ?>
        <fieldset class="fs-slots" data-slot-picker>
          <legend class="fs-slots__legend">
            <span class="fs-label text-fg-subtle">Fascia</span>
<?php if ($live !== null): ?>
            <span class="fs-label text-fg-muted"><?= $view->e(ucfirst($dayLabel)) ?></span>
<?php else: ?>
            <span class="fs-tag fs-tag--placeholder">[Fasce illustrative]</span>
<?php endif; ?>
          </legend>
<?php foreach ($catalog->slots() as $slot): ?>
          <?= $view->partial('partials/landing/slot', ['slot' => $slot, 'checked' => $chosen !== null && $slot->time === $chosen->time]) ?>
<?php endforeach; ?>
        </fieldset>
<?php elseif ($timetable !== null && $timetable->times !== []): ?>
        <div class="flex flex-col gap-1">
          <p class="fs-slots__legend"><span class="fs-label text-fg-subtle">Fascia</span><span class="fs-label text-fg-muted"><?= $view->e(ucfirst($dayLabel)) ?></span></p>
          <ul class="fs-slots" aria-label="<?= $view->e('Fasce di ' . $dayLabel) ?>">
<?php foreach ($timetable->times as $time): ?>
            <li class="fs-slot__row fs-slot__row--plain"><span class="text-body tabular-nums lining-nums"><span class="sr-only">Fascia delle </span><?= $view->e($time->value) ?></span></li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>
<?php if ($online): ?>
        <div class="flex flex-col items-start sm:flex-row sm:gap-x-6">
          <a href="<?= $view->e(BookingUrls::day($day)) ?>" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">
            <?= $view->icon('calendar') ?>
            <span class="fs-btn__label">Altri giorni</span>
          </a>
<?php if ($accountUrl !== null): ?>
          <a href="<?= $view->e($accountUrl) ?>" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">
            <?= $view->icon('user') ?>
            <span class="fs-btn__label">Le tue prenotazioni</span>
          </a>
<?php endif; ?>
        </div>
<?php endif; ?>
      </div>

      <div class="flex flex-col gap-8">
        <div class="fs-panel fs-reveal flex flex-col gap-6 p-6 lg:p-8" data-reveal>
          <div class="flex flex-col gap-2">
            <p class="fs-label text-fg-subtle">La tua fascia</p>
<?php if ($chosen !== null): ?>
            <div class="flex flex-col gap-2" aria-live="polite" aria-atomic="true">
              <p class="font-display text-countdown-lg text-fg tabular-nums lining-nums" data-slot-time><?= $view->e($chosen->time) ?></p>
              <p class="text-body-sm text-fg-muted">Il tuo è il posto <span data-slot-seat><?= $view->e($chosen->nextSeat()) ?></span> di <?= $view->e($slots->capacity()) ?>.</p>
            </div>
<?php elseif ($timetable !== null && $day !== null): ?>
            <p class="text-body text-fg-muted">Scegli l'orario nella pagina delle prenotazioni: lì vedi i posti liberi e confermi.</p>
<?php elseif ($day !== null): ?>
            <p class="text-body text-fg-muted">Le fasce dei prossimi giorni sono al completo. Si libera un posto quando qualcuno disdice.</p>
<?php else: ?>
            <p class="text-body text-fg-muted">Nessuna fascia da prenotare nei prossimi giorni.</p>
<?php endif; ?>
          </div>
          <div class="h-px bg-hairline" aria-hidden="true"></div>
<?php if ($online): ?>
          <?= $view->partial('partials/landing/step', ['number' => '02', 'title' => 'Prenoti il posto', 'text' => 'Accedi o crea l\'account e conferma: ti arriva un\'email.']) ?>
          <div class="flex flex-col gap-3">
<?php if ($form): ?>
            <button type="submit" class="fs-btn fs-btn--primary fs-btn--block" data-primary-cta>Prenota il posto</button>
<?php elseif ($day !== null): ?>
            <a href="<?= $view->e(BookingUrls::day($day)) ?>" class="fs-btn fs-btn--primary fs-btn--block" data-primary-cta>
              <span><?= $timetable !== null ? 'Scegli la fascia' : 'Vedi tutti i giorni' ?></span>
              <?= $view->icon('arrow', 'fs-btn__arrow') ?>
            </a>
<?php endif; ?>
            <?= $view->partial('partials/landing/cta-whatsapp', ['href' => $whatsapp, 'class' => 'fs-btn--block']) ?>
          </div>
<?php else: ?>
          <?= $view->partial('partials/landing/step', ['number' => '02', 'title' => 'Prenoti il posto', 'text' => 'Dal sito, presto. Oggi su WhatsApp.']) ?>
          <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
              <button type="button" class="fs-btn fs-btn--primary min-w-0 flex-1" aria-disabled="true" aria-describedby="prenota-presto">Prenota il posto</button>
              <span id="prenota-presto" class="fs-tag">Presto</span>
            </div>
            <?= $view->partial('partials/landing/cta-whatsapp', ['href' => $whatsapp, 'class' => 'fs-btn--block']) ?>
          </div>
<?php endif; ?>
        </div>
        <?= $view->partial('partials/landing/step', ['number' => '03', 'title' => 'Ti alleni', 'text' => 'Vieni in ' . $address . ' all\'ora scelta.']) ?>
      </div>
<?php if ($form): ?>
    </form>
<?php else: ?>
    </div>
<?php endif; ?>

  </div>
</section>
