<?php

declare(strict_types=1);

use FourStreet\Booking\AgendaEntry;
use FourStreet\Booking\BookingUrls;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * "Le tue prenotazioni" nella pagina dell'account: le prossime, dalla più vicina, con giorno, orario e posto
 * e il link "Disdici" (che chiede conferma). Quella in corso resta fino alla fine della fascia, senza disdetta.
 * Sopra, l'avviso dopo una prenotazione o una disdetta; sotto, il link per prenotare (se si prenota dal sito).
 *
 * @var Template $view
 * @var list<AgendaEntry> $entries
 * @var Alert|null $alert
 * @var bool $unavailable le prenotazioni non si sono potute leggere (l'errore è nel log)
 * @var int $capacity
 * @var string|null $bookUrl la pagina delle fasce, se le prenotazioni dal sito sono attive
 * @var ItalianDateFormatter $dates
 */
?>
<section aria-labelledby="prenotazioni-titolo" class="fs-account__section">
  <h2 id="prenotazioni-titolo" class="fs-label text-fg-subtle">Le tue prenotazioni</h2>
<?php if ($alert !== null): ?>
  <?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?php if ($unavailable): ?>
  <div class="fs-panel fs-account__empty" data-bookings>
    <p class="text-body text-fg-muted">Le prenotazioni non si possono mostrare adesso. Riprova tra qualche minuto.</p>
  </div>
<?php elseif ($entries === []): ?>
  <div class="fs-panel fs-account__empty" data-bookings>
    <p class="text-body text-fg-muted">Nessuna prenotazione.</p>
  </div>
<?php else: ?>
  <ol class="fs-agenda" data-bookings>
<?php foreach ($entries as $entry):
    $booking = $entry->booking;
    $dayText = $dates->format($booking->date->calendarDay(), withYear: false, capitalize: false);
    $start = $entry->startsAt->format('H:i');
    $end = $entry->endsAt->format('H:i');
    ?>
    <li class="fs-agenda__item<?= $entry->inProgress ? ' fs-agenda__item--now' : '' ?>">
      <span class="fs-agenda__date"><?= $view->partial('partials/booking/day-badge', ['date' => $booking->date, 'dates' => $dates, 'withMonth' => true]) ?></span>
      <div class="fs-agenda__body">
        <p class="fs-agenda__when"><span class="sr-only"><?= $view->e(ucfirst($dayText) . ', dalle ' . $start . ' alle ' . $end) ?></span><span aria-hidden="true"><?= $view->e($start) ?> – <?= $view->e($end) ?></span></p>
        <p class="fs-agenda__meta">Posto <?= $view->e($booking->seat()) ?> di <?= $view->e($capacity) ?></p>
      </div>
<?php if ($entry->cancellable): ?>
      <a href="<?= $view->e(BookingUrls::cancel($booking->id)) ?>" class="fs-btn fs-btn--ghost fs-btn--sm fs-agenda__action" aria-label="<?= $view->e('Disdici la prenotazione di ' . $dayText . ' alle ' . $start) ?>"><span class="fs-btn__label">Disdici</span></a>
<?php elseif ($entry->inProgress): ?>
      <span class="fs-tag fs-tag--event fs-agenda__action"><span class="fs-tag__dot fs-anim-breathe"></span>In corso</span>
<?php else: ?>
      <span class="fs-agenda__action fs-micro text-fg-subtle">Disdetta chiusa</span>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ol>
<?php endif; ?>
<?php if ($bookUrl !== null): ?>
  <a href="<?= $view->e($bookUrl) ?>" class="fs-btn fs-btn--primary fs-btn--block sm:w-auto sm:self-start">
    <?= $view->icon('calendar') ?>
    <span><?= $entries === [] ? 'Prenota una fascia' : 'Prenota un\'altra fascia' ?></span>
  </a>
<?php endif; ?>
</section>
