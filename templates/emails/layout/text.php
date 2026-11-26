<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Layout del testo semplice delle email: il marchio, il messaggio e la firma con l'indirizzo.
 * È testo, non HTML: niente escape (i valori non finiscono mai in una pagina web).
 *
 * @var Template $view
 * @var Html $content
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

echo <<<TEXT
    4STREET

    {$content}

    --
    {$siteName} · {$address}
    {$siteUrl}

    TEXT;
