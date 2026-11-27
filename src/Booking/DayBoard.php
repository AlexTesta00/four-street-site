<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * La pagina delle fasce: i giorni che si prenotano, quello scelto con le sue fasce e, per chi ha fatto
 * l'accesso, quante prenotazioni future ha già.
 */
final readonly class DayBoard
{
    /**
     * @param list<DayOption> $days i giorni prenotabili, da oggi (o dall'inaugurazione)
     * @param DayOption|null $selected il giorno mostrato; null solo se non c'è ancora nessun giorno
     * @param bool $requestedUnavailable il giorno chiesto non è fra quelli prenotabili: si mostra il primo libero
     * @param int $upcoming le prenotazioni future attive del visitatore (0 per un ospite)
     * @param positive-int $maxUpcoming il massimo di prenotazioni future per cliente
     * @param positive-int $capacity i posti di ogni fascia
     */
    public function __construct(
        public array $days,
        public ?DayOption $selected,
        public bool $requestedUnavailable,
        public int $upcoming,
        public int $maxUpcoming,
        public int $capacity,
    ) {}

    /** Il visitatore ha già il massimo di prenotazioni future: per ora non ne fa altre. */
    public function reachedMax(): bool
    {
        return $this->upcoming >= $this->maxUpcoming;
    }
}
