<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\BookingUrls;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * Una fascia che non si può prenotare, con il motivo e la strada per proseguire: un solo bottone principale.
 * 404 se la fascia non c'è; 409 se è piena, se il cliente ha già prenotato quel giorno, se ha già il massimo
 * di prenotazioni future o se la prenotazione è chiusa; 429 (con l'avviso) dopo troppi tentativi.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var BookingStatus $status
 * @var string|null $slotDay "lunedì 7 dicembre"
 * @var string|null $slotTime "18:30"
 * @var string|null $existingTime l'orario della prenotazione che il cliente ha già quel giorno
 * @var bool $existingCancellable quella prenotazione si disdice ancora
 * @var int $maxUpcoming
 * @var string $bookingDeadline "fino all'inizio della fascia"
 * @var string $dayUrl le fasce del giorno chiesto
 * @var Alert|null $alert troppi tentativi
 */

// Per tutti gli esiti tranne "non trovata" la fascia è nota: giorno e orario ci sono.
$day = (string) $slotDay;
$time = (string) $slotTime;
[$heading, $lead, $primary, $secondary] = match ($status) {
    BookingStatus::Full => [
        'Fascia al completo.',
        sprintf('Mentre sceglievi, gli ultimi posti della fascia delle %s di %s sono stati presi. Scegli un\'altra fascia.', $time, $day),
        ['Scegli un\'altra fascia', $dayUrl],
        null,
    ],
    BookingStatus::AlreadyBooked => $existingCancellable ? [
        'Hai già una prenotazione.',
        sprintf('%s sei prenotato alle %s. Si prenota una fascia al giorno: per cambiarla, disdici prima quella.', ucfirst($day), (string) $existingTime),
        ['Le tue prenotazioni', AccountUrls::HOME],
        ['Scegli un altro giorno', BookingUrls::DAY],
    ] : [
        // Quella di oggi è già iniziata (o passata): non si disdice più, quindi la pagina non lo propone.
        'Hai già una prenotazione.',
        sprintf('%s sei prenotato alle %s. Si prenota una fascia al giorno: scegli un altro giorno.', ucfirst($day), (string) $existingTime),
        ['Scegli un altro giorno', BookingUrls::DAY],
        null,
    ],
    // Il massimo lo sceglie il titolare dal pannello, e può abbassarlo quando un cliente ne ha già di più: la pagina
    // dice il massimo, non quante ne ha il cliente.
    BookingStatus::TooManyUpcoming => [
        'Hai già il massimo di prenotazioni.',
        sprintf(
            'Si possono avere al massimo %s in arrivo, una al giorno. Quando una è passata, o se ne disdici una, puoi prenotare di nuovo.',
            $maxUpcoming === 1 ? 'una prenotazione' : $maxUpcoming . ' prenotazioni',
        ),
        ['Le tue prenotazioni', AccountUrls::HOME],
        null,
    ],
    BookingStatus::Closed => [
        'Prenotazioni chiuse.',
        sprintf('La fascia delle %s di %s non si prenota più: si prenota %s.', $time, $day, $bookingDeadline),
        ['Scegli un\'altra fascia', $dayUrl],
        null,
    ],
    default => [
        'Fascia non trovata.',
        'Questa fascia non c\'è, o non si prenota ancora. Scegli fra quelle dei prossimi giorni.',
        ['Scegli la fascia', BookingUrls::DAY],
        null,
    ],
};

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · ' . rtrim($heading, '.'),
    'eyebrow' => 'Prenotazione',
    'heading' => $heading,
    'lead' => $lead,
    'compact' => true,
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<div class="fs-actions">
  <a href="<?= $view->e($primary[1]) ?>" class="fs-btn fs-btn--primary fs-btn--block"><?= $view->e($primary[0]) ?></a>
<?php if ($secondary !== null): ?>
  <a href="<?= $view->e($secondary[1]) ?>" class="fs-btn fs-btn--secondary fs-btn--block"><?= $view->e($secondary[0]) ?></a>
<?php endif; ?>
</div>
