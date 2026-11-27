<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;

/**
 * Come diventa un giorno dopo la modifica: torna alla settimana tipo (Week), ha un orario suo (Changed) o è chiuso
 * (Closed). $before e $after sono gli orari di prima e di dopo, per sapere quali fasce spariscono.
 */
final readonly class DayWrite
{
    /**
     * @param list<SlotTime> $before gli orari di adesso
     * @param list<SlotTime> $after gli orari dopo la modifica, in ordine; [] se chiuso
     * @param string|null $note la nota, solo per un giorno chiuso
     */
    public function __construct(
        public LocalDate $date,
        public DayKind $kind,
        public array $before,
        public array $after,
        public ?string $note = null,
    ) {}

    /**
     * Gli orari che spariscono: le loro prenotazioni attive vanno disdette. Un giorno chiuso non ha più nessuna fascia,
     * nemmeno quelle che l'orario aveva già perso (le prenotazioni "fuori dall'orario").
     */
    public function removes(SlotTime $time): bool
    {
        foreach ($this->after as $kept) {
            if ($kept->equals($time)) {
                return false;
            }
        }
        if ($this->after === []) {
            return true;
        }
        foreach ($this->before as $old) {
            if ($old->equals($time)) {
                return true;
            }
        }

        return false;
    }
}
