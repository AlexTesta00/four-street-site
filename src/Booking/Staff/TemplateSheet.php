<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\WeeklySchedule;

/**
 * La settimana tipo, come la cambia il titolare: per ogni giorno della settimana gli orari d'inizio delle fasce.
 * Vale per i giorni senza un orario loro; una modifica vale da $from (domani), oggi e il passato restano com'erano.
 */
final readonly class TemplateSheet
{
    /**
     * @param positive-int $slotMinutes quanto dura una fascia (config/site.php)
     */
    public function __construct(
        public WeeklySchedule $week,
        public LocalDate $from,
        public int $slotMinutes,
    ) {}
}
