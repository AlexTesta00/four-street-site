<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * L'avviso di una prenotazione disdetta dal cliente, in testo semplice.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi,"
 * @var string $day "lunedì 7 dicembre 2026"
 * @var string $time "18:30"
 * @var string $endTime "19:30"
 * @var string|null $bookUrl la pagina delle fasce, se dal sito si prenota
 * @var string $resetUrl la pagina della password dimenticata
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

$again = $bookUrl === null ? '' : <<<TEXT
    Vuoi venire un altro giorno? Scegli la fascia:

    {$bookUrl}


    TEXT;

echo <<<TEXT
    {$greeting}

    hai disdetto la prenotazione di {$day}, dalle {$time} alle {$endTime}: il posto è tornato libero.

    {$again}Se non sei stato tu, scegli subito una nuova password:

    {$resetUrl}
    TEXT;
