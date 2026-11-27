<?php

declare(strict_types=1);

namespace FourStreet\Booking\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\Session\Visitor;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotBoard;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /prenota/: la scelta del giorno (link ?giorno=AAAA-MM-GG, senza JavaScript) e le fasce di quel giorno
 * con i posti veri. Pubblica: riconosce il visitatore per dirgli se quel giorno ha già prenotato,
 * ma la prenotazione vera chiede l'accesso (la pagina di conferma).
 */
final readonly class DayController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private SlotBoard $board,
        private BlockedBookingPage $blocked,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $day = $request->queryParam(BookingUrls::DAY_PARAMETER);
        $requested = $day === null ? null : LocalDate::tryFrom($day);
        if ($day !== null && $requested === null) {
            return $this->blocked->render(BookingResult::notFound());
        }

        $visitor = Visitor::of($request)->user;

        return $this->pages->render('day', [
            'board' => $this->board->day($requested, $visitor),
            'visitor' => $visitor,
            'dates' => $this->dates,
            'returnTo' => ReturnPath::fromRequest($request),
        ]);
    }
}
