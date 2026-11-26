<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Email di conferma dopo la registrazione, in testo semplice.
 *
 * @var Template $view
 * @var string $greeting "Ciao,": l'email non è ancora confermata, quindi niente nome
 * @var string $url il link di conferma
 * @var int $hours quanto vale il link
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/text', ['siteName' => $siteName, 'address' => $address, 'siteUrl' => $siteUrl]);

echo <<<TEXT
    {$greeting}

    conferma la tua email per attivare l'account 4Street: apri questo link.

    {$url}

    Il link vale {$hours} ore e si usa una volta sola. Se non hai creato tu l'account, ignora questa email: senza conferma l'account non si attiva.
    TEXT;
