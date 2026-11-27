<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Quale posto dare a chi prenota, dati quelli già presi. Il database garantisce che un posto sia di una
 * prenotazione sola: se due persone scelgono insieme lo stesso, una delle due riprova con il successivo.
 */
interface SeatChooser
{
    /**
     * @param list<int> $taken i posti già presi della fascia
     * @param positive-int $capacity i posti della fascia
     *
     * @return int|null il posto da provare (da 1 a $capacity), oppure null se la fascia è piena
     */
    public function choose(array $taken, int $capacity): ?int;
}
