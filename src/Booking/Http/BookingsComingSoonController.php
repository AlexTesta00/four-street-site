<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * /prenota/ e /prenota/conferma/ quando le prenotazioni dal sito non sono attive (booking.online a false,
 * oppure il server senza config/local.php): 503 con una pagina cortese che rimanda a WhatsApp.
 */
final readonly class BookingsComingSoonController implements Controller
{
    public function __construct(
        private AccountPages $pages,
    ) {}

    public function __invoke(Request $request): Response
    {
        return $this->pages->render('coming-soon', [], 503);
    }
}
