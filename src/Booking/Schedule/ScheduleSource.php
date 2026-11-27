<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\BookingRules;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\WeeklySchedule;

/**
 * Da dove vengono l'orario e le regole delle prenotazioni: dal file (config/site.php, FileSchedule) oppure dal
 * database (PdoSchedule), dove li salva il titolare dal pannello. BookingCalendar li legge solo da qui: landing,
 * /prenota/, account, email e pannello vedono lo stesso orario.
 */
interface ScheduleSource
{
    /** Le regole in vigore: quelle del file, con al loro posto i valori che il titolare ha salvato. */
    public function rules(): BookingRules;

    /**
     * L'orario di quel giorno: la settimana tipo, un orario suo o una chiusura. L'inaugurazione e l'ora che il
     * cambio dell'ora salta le considera BookingCalendar.
     */
    public function day(LocalDate $date): DayPlan;

    /** La settimana tipo di adesso: quella che vale da domani per i giorni senza un orario loro. */
    public function week(): WeeklySchedule;
}
