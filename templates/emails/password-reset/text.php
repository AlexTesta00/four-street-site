<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Il link per scegliere una nuova password, in testo semplice.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi," a email confermata, altrimenti "Ciao,"
 * @var string $url il link per la nuova password
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

echo <<<TEXT
    {$greeting}

    hai chiesto di cambiare la password del tuo account 4Street. Apri questo link e scegli quella nuova:

    {$url}

    Il link vale un'ora e si usa una volta sola. Se non l'hai chiesto tu, ignora questa email: la password resta quella di prima.
    TEXT;
