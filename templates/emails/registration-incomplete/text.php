<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Qualcuno ha chiesto una nuova password per un account mai confermato, in testo semplice:
 * la registrazione non è completa e va rifatta.
 *
 * @var Template $view
 * @var string $greeting "Ciao,": l'email non è confermata, quindi niente nome
 * @var string $registerUrl la pagina di registrazione
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

echo <<<TEXT
    {$greeting}

    qualcuno, forse tu, ha chiesto una nuova password per l'account 4Street di questo indirizzo. La registrazione però non è stata completata: l'email non è mai stata confermata, quindi l'account non è attivo.

    Registrati di nuovo con la password che preferisci: ti mandiamo un nuovo link per confermare l'email.

    {$registerUrl}

    Se non sei stato tu, ignora questa email: senza conferma l'account non si attiva.
    TEXT;
