<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Qualcuno ha provato a registrarsi con un'email che ha già un account confermato, in testo semplice.
 *
 * @var Template $view
 * @var string $greeting "Ciao Mario Rossi," a email confermata, altrimenti "Ciao,"
 * @var string $loginUrl
 * @var string $resetUrl
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

echo <<<TEXT
    {$greeting}

    qualcuno, forse tu, ha provato a creare un account 4Street con questo indirizzo. L'account c'è già: accedi con la tua password.

    {$loginUrl}

    Non ricordi la password? Scegline una nuova:

    {$resetUrl}

    Se non sei stato tu, ignora questa email: il tuo account non è cambiato.
    TEXT;
