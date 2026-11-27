<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\View\Template;

/**
 * Nella pagina dell'account del titolare: il link al pannello delle prenotazioni. Un cliente non lo vede mai.
 *
 * @var Template $view
 */
?>
<section aria-labelledby="gestione-titolo" class="fs-account__section">
  <h2 id="gestione-titolo" class="fs-label text-fg-subtle">Gestione</h2>
  <a href="<?= $view->e(AdminUrls::HOME) ?>" class="fs-btn fs-btn--secondary fs-btn--block sm:w-auto sm:self-start">
    <?= $view->icon('list') ?>
    <span>Gestione prenotazioni</span>
  </a>
</section>
