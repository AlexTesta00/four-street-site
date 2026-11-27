<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingUrls;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Staff\DayRoster;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /gestione/ e /gestione/?giorno=AAAA-MM-GG: il giorno nel pannello del titolare. Le fasce con chi è prenotato,
 * il giorno prima e quello dopo (anche nel passato, da guardare soltanto), la scelta rapida dei giorni e, dopo
 * un redirect, l'avviso su una prenotazione inserita o disdetta. Un giorno scritto male, o a più di dieci anni da
 * quello di oggi: 404.
 */
final readonly class RosterController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private DayRoster $roster,
        private StaffBlockedPage $blocked,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $day = $request->queryParam(AdminUrls::DAY_PARAMETER);
        $requested = $day === null ? null : LocalDate::tryFrom($day);
        // Un giorno scritto male, o oltre la portata del pannello: non c'è.
        $roster = $day !== null && $requested === null ? null : $this->roster->day($requested);
        if ($roster === null) {
            return $this->blocked->render(BookingResult::notFound());
        }

        return $this->pages->render('day', [
            'roster' => $roster,
            'alert' => AdminNotice::fromRequest($request)?->alert($this->noticeEntry($request), $this->dates),
            'dates' => $this->dates,
        ]);
    }

    /** La prenotazione di cui parla l'avviso: nell'indirizzo c'è solo il suo numero. */
    private function noticeEntry(Request $request): ?RosterEntry
    {
        $id = BookingUrls::bookingId($request->queryParam(AdminUrls::BOOKING_PARAMETER));

        return $id === null ? null : $this->roster->entry($id);
    }
}
