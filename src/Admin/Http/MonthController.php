<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\Staff\Month;
use FourStreet\Booking\Staff\MonthOverview;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /gestione/calendario/ e /gestione/calendario/?mese=AAAA-MM: il mese a colpo d'occhio nel pannello del titolare.
 * Per ogni giorno se è aperto o chiuso (con la nota della chiusura), quante fasce e quante persone prenotate, le fasce
 * piene e le prenotazioni fuori dall'orario; ogni giorno porta al giorno del pannello. Senza mese, quello di oggi;
 * il mese prima e quello dopo fino al bordo della portata del pannello (dieci anni). Un mese scritto male, o oltre
 * la portata: 404, come un giorno scritto male in /gestione/.
 */
final readonly class MonthController implements Controller
{
    /** I mesi a parole, per il titolo ("Dicembre 2026") e le settimane ("Dal 7 al 13 dicembre"). */
    private const array MONTHS = [
        1 => 'gennaio',
        'febbraio',
        'marzo',
        'aprile',
        'maggio',
        'giugno',
        'luglio',
        'agosto',
        'settembre',
        'ottobre',
        'novembre',
        'dicembre',
    ];

    public function __construct(
        private AccountPages $pages,
        private MonthOverview $months,
        private StaffBlockedPage $blocked,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $value = $request->queryParam(AdminUrls::MONTH_PARAMETER);
        $requested = $value === null ? null : Month::tryFrom($value);
        // Un mese scritto male, o oltre la portata del pannello: non c'è.
        $sheet = $value !== null && $requested === null ? null : $this->months->month($requested);
        if ($sheet === null) {
            return $this->blocked->render(BookingResult::notFound());
        }

        return $this->pages->render('month', [
            'sheet' => $sheet,
            'monthName' => self::name($sheet->month),
            'monthWord' => self::word($sheet->month),
            'previousName' => $sheet->previous === null ? null : self::name($sheet->previous),
            'nextName' => $sheet->next === null ? null : self::name($sheet->next),
            'dates' => $this->dates,
        ]);
    }

    /** Il mese a parole, con l'anno: "dicembre 2026". */
    private static function name(Month $month): string
    {
        return self::word($month) . ' ' . $month->first()->calendarDay()->format('Y');
    }

    /** Il mese a parole: "dicembre". */
    private static function word(Month $month): string
    {
        return self::MONTHS[(int) $month->first()->calendarDay()->format('n')];
    }
}
