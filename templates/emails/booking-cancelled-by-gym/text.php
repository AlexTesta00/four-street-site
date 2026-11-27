<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * L'avviso di una prenotazione disdetta dalla palestra, in testo semplice. Niente "se non sei stato tu":
 * la disdetta non l'ha fatta il cliente.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi,"
 * @var string $day "lunedì 7 dicembre 2026"
 * @var string $time "18:30"
 * @var string $endTime "19:30"
 * @var string|null $bookUrl la pagina delle fasce, se dal sito si prenota
 * @var string|null $whatsappUrl il WhatsApp della palestra, se c'è
 * @var string|null $reason perché, quando la disdetta viene da un cambio d'orario: "Quel giorno la palestra resta chiusa."
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

$paragraphs = [
    $greeting,
    sprintf('la palestra ha disdetto la tua prenotazione di %s, dalle %s alle %s.%s Ci dispiace per il cambio di programma.', $day, $time, $endTime, $reason === null ? '' : ' ' . $reason),
];
if ($bookUrl !== null) {
    $paragraphs[] = "Vuoi venire un altro giorno? Scegli la fascia:\n\n" . $bookUrl;
}
if ($whatsappUrl !== null) {
    $paragraphs[] = "Per qualsiasi domanda scrivici su WhatsApp:\n\n" . $whatsappUrl;
}

echo implode("\n\n", $paragraphs);
