<?php

declare(strict_types=1);

use FourStreet\Booking\Slot;
use FourStreet\View\Template;

/**
 * I posti di una fascia, un quadrato per posto: pieni quelli presi, vuoto con il filetto quelli liberi.
 * Il primo libero (quello che toccherebbe) è segnato come "prossimo": nella fascia scelta diventa oro.
 * Con $mine il posto del cliente è oro e nessun altro è segnato. Solo per gli occhi: il testo vicino
 * dice tutto a parole.
 *
 * @var Template $view
 * @var Slot $slot
 * @var int|null $mine il posto del cliente, se ne ha uno in questa fascia
 * @var bool|null $medium facoltativo: quadrati più grandi, da leggere a colpo d'occhio (il pannello del titolare)
 */

$next = $mine === null ? $slot->nextSeat() : null;
?>
<span class="fs-seats<?= ($medium ?? false) ? ' fs-seats--md' : '' ?>" aria-hidden="true">
<?php for ($seat = 1; $seat <= $slot->capacity; ++$seat): ?>
      <span class="fs-seat<?= match (true) {
          $seat === $mine => ' fs-seat--mine',
          $slot->isSeatTaken($seat) => ' fs-seat--taken',
          $seat === $next => ' fs-seat--next',
          default => '',
      } ?>"></span>
<?php endfor; ?>
    </span>
