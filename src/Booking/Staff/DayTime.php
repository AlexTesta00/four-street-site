<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\SlotTime;

/**
 * Un orario nell'orario di un giorno, come lo vede il titolare quando lo cambia: quante persone ci sono prenotate
 * (togliendolo, quelle prenotazioni si disdicono) e se è già cominciato (allora non si tocca più).
 */
final readonly class DayTime
{
    /**
     * @param int $booked le prenotazioni attive di quella fascia
     * @param bool $started la fascia è già cominciata (oggi) o il giorno è passato
     * @param bool $exists la fascia esiste davvero: non prima dell'inaugurazione, non nell'ora che il cambio dell'ora salta
     */
    public function __construct(
        public SlotTime $time,
        public int $booked,
        public bool $started,
        public bool $exists,
    ) {}
}
