<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Le prenotazioni dell'account. Oggi prenotare dal sito non si può ancora, quindi l'elenco è vuoto:
 * quando arriveranno le prenotazioni, AccountController passerà qui le fasce prenotate e questo partial
 * le elencherà (data-bookings), con lo stesso stato vuoto quando non ce ne sono.
 *
 * @var Template $view
 */
?>
<div class="fs-panel fs-account__empty" data-bookings>
  <p class="text-body text-fg-muted">Nessuna prenotazione.</p>
</div>
