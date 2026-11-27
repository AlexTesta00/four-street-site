<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

/**
 * Che cosa chiede il titolare, quando cambia l'orario.
 */
enum ChangeKind
{
    /** Un orario in più nella settimana tipo, per quel giorno della settimana (da domani). */
    case AddWeekdayTime;

    /** Un orario in meno nella settimana tipo, per quel giorno della settimana (da domani). */
    case RemoveWeekdayTime;

    /** Un orario in più in un giorno preciso. */
    case AddTime;

    /** Un orario in meno in un giorno preciso. */
    case RemoveTime;

    /** Uno o più giorni di fila chiusi, con una nota facoltativa. */
    case Close;

    /** I giorni chiusi di un intervallo tornano alla settimana tipo. */
    case Reopen;

    /** Un giorno con un orario suo torna alla settimana tipo. */
    case BackToWeek;

    /** La settimana che comincia quel lunedì, copiata nella successiva. */
    case CopyWeek;
}
