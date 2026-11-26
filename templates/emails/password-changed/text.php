<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Avviso dopo il cambio della password, in testo semplice.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi," a email confermata, altrimenti "Ciao,"
 * @var string $when "giovedì 26 novembre 2026 alle 14:05", ora di San Marino
 * @var string $resetUrl la pagina della password dimenticata
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

echo <<<TEXT
    {$greeting}

    la password del tuo account 4Street è stata cambiata {$when}. Per sicurezza abbiamo chiuso l'accesso su tutti i dispositivi.

    Se non sei stato tu, scegli subito una nuova password:

    {$resetUrl}

    Se sei stato tu, non devi fare nulla.
    TEXT;
