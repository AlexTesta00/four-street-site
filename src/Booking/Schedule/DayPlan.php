<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;

/**
 * L'orario di un giorno: gli orari d'inizio delle sue fasce, dal più presto, e da dove vengono. È l'orario
 * scritto, prima dell'inaugurazione e del cambio dell'ora: quali fasce esistono davvero lo dice BookingCalendar.
 * La nota è a uso interno del titolare ("ferie"): non va mai in una pagina pubblica, in un'email né in un indirizzo.
 */
final readonly class DayPlan
{
    /**
     * @param list<SlotTime> $times gli orari d'inizio, dal più presto; [] se il giorno non ha fasce
     * @param string|null $note la nota del titolare, solo per un giorno con un orario suo
     */
    public function __construct(
        public LocalDate $date,
        public DayKind $kind,
        public array $times,
        public ?string $note = null,
    ) {}

    /** Il titolare (o il file) l'ha chiuso. Un giorno della settimana tipo senza fasce non è una chiusura. */
    public function isClosed(): bool
    {
        return $this->kind === DayKind::Closed;
    }

    /** Ha un orario diverso dalla settimana tipo: chiuso, oppure con fasce sue. */
    public function isException(): bool
    {
        return $this->kind !== DayKind::Week;
    }

    public function has(SlotTime $time): bool
    {
        foreach ($this->times as $existing) {
            if ($existing->equals($time)) {
                return true;
            }
        }

        return false;
    }
}
