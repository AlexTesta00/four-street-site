<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Inizia a scrivere, poi si rompe: il buffer non deve restare aperto.
 *
 * @var Template $view
 */

ob_start();
?>
<p>mezza pagina</p>
<?php
throw new RuntimeException('Template rotto.');
