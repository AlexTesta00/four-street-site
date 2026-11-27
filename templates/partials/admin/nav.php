<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\View\Template;

/**
 * La navigazione del pannello del titolare, in cima a ogni sua pagina (il layout la mette sopra l'occhiello):
 * Oggi, Calendario, Orari, Chiusure, Impostazioni. Sul telefono è una riga sola che scorre di lato, larga fino ai bordi
 * dello schermo (la voce tagliata al bordo dice che ce n'è un'altra); lo script porta in vista la voce corrente.
 * La sezione corrente ha il filetto oro: aria-current="page" se la pagina è proprio quella del link, altrimenti
 * aria-current="true" (una pagina della sezione: un altro giorno, una settimana dell'orario, un modulo).
 *
 * @var Template $view
 * @var string $current la sezione: "day", "calendar", "schedule", "closures" oppure "settings"
 * @var bool|null $exact facoltativo: la pagina è proprio quella del link della sezione (predefinito true)
 */

$items = [
    'day' => ['Oggi', AdminUrls::HOME],
    'calendar' => ['Calendario', AdminUrls::CALENDAR],
    'schedule' => ['Orari', AdminUrls::SCHEDULE],
    'closures' => ['Chiusure', AdminUrls::CLOSURES],
    'settings' => ['Impostazioni', AdminUrls::SETTINGS],
];
?>
<nav class="fs-adminnav" aria-label="Pannello del titolare" data-panel-nav>
  <ul class="fs-adminnav__list">
<?php foreach ($items as $key => [$label, $href]): ?>
    <li><a href="<?= $view->e($href) ?>" class="fs-adminnav__link"<?= $key === $current ? ' aria-current="' . (($exact ?? true) ? 'page' : 'true') . '"' : '' ?>><?= $view->e($label) ?></a></li>
<?php endforeach; ?>
  </ul>
</nav>
