<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * La conferma di una prenotazione, in testo semplice.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi,"
 * @var string $day "lunedì 7 dicembre 2026"
 * @var string $time "18:30"
 * @var string $endTime "19:30"
 * @var int $seat
 * @var int $capacity
 * @var string $accountUrl la pagina dell'account, dove si disdice
 * @var string $cancellation fino a quando si disdice: "fino all'inizio della fascia"
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

$dayTitle = ucfirst($day);

echo <<<TEXT
    {$greeting}

    il tuo posto è prenotato. Ti aspettiamo.

    Giorno: {$dayTitle}
    Orario: {$time} – {$endTime}
    Posto: {$seat} di {$capacity}
    Dove: {$address}

    In allegato c'è l'evento da aggiungere al calendario.

    Se non puoi venire, disdici dal tuo account {$cancellation}: il posto torna libero per un altro.

    {$accountUrl}
    TEXT;
