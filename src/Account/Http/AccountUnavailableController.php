<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Ogni pagina dell'area clienti quando il server non ha config/local.php (database e posta non configurati):
 * 503 con una pagina cortese. Il resto del sito funziona come sempre.
 */
final readonly class AccountUnavailableController implements Controller
{
    public function __construct(
        private AccountPages $pages,
    ) {}

    public function __invoke(Request $request): Response
    {
        return $this->pages->render('unavailable', [], 503);
    }
}
