<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\DayPlan;

/**
 * Un giorno nel calendario del mese del pannello: com'è l'orario, quante fasce e quante persone prenotate, e i due
 * segnali da guardare: fasce piene e prenotazioni in fasce che l'orario non ha più ("fuori dall'orario").
 */
final readonly class MonthDay
{
    /**
     * @param DayPlan $plan l'orario scritto di quel giorno (settimana tipo, orario suo, chiusura), con la nota del titolare
     * @param int $slots le fasce che esistono davvero (dall'inaugurazione in poi, senza l'ora che il cambio dell'ora salta)
     * @param int $booked le prenotazioni attive del giorno, comprese quelle fuori dall'orario
     * @param int $fullSlots le fasce con tutti i posti presi
     * @param int $outOfSchedule le prenotazioni attive in fasce che l'orario di quel giorno non ha più
     */
    public function __construct(
        public LocalDate $date,
        public DayPlan $plan,
        public int $slots,
        public int $booked,
        public int $fullSlots,
        public int $outOfSchedule,
        public bool $isToday,
        public bool $isPast,
        public bool $isBeforeOpening,
    ) {}

    /** Nessuna fascia: chiuso, la domenica, o prima dell'inaugurazione. */
    public function isClosed(): bool
    {
        return $this->slots === 0;
    }
}
