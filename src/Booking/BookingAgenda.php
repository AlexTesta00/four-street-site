<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\User;
use FourStreet\Clock\Clock;

/**
 * Le prenotazioni di un cliente, per la pagina del suo account.
 */
final readonly class BookingAgenda
{
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private Clock $clock,
    ) {}

    /**
     * Le prenotazioni attive non ancora finite, dalla più vicina: quelle passate non ci sono,
     * quella in corso sì (fino alla fine della fascia).
     *
     * @return list<AgendaEntry>
     */
    public function upcoming(User $customer): array
    {
        $now = $this->clock->now();

        $entries = [];
        // Le fasce finiscono entro la mezzanotte: da oggi in poi c'è tutto ciò che non è finito.
        foreach ($this->bookings->activeFrom($customer->id, $this->calendar->today($now)) as $booking) {
            $endsAt = $this->calendar->endOf($booking->startsAt);
            if ($endsAt <= $now) {
                continue;
            }
            $entries[] = new AgendaEntry(
                $booking,
                $this->calendar->local($booking->startsAt),
                $endsAt,
                $booking->startsAt <= $now,
                $this->calendar->canCancel($booking->startsAt, $now),
            );
        }

        return $entries;
    }

    /** Una prenotazione del cliente, anche disdetta (per l'avviso che ne parla), oppure null se non è sua. */
    public function find(User $customer, int $bookingId): ?Booking
    {
        $booking = $this->bookings->find($bookingId);

        return $booking !== null && $booking->belongsTo($customer->id) ? $booking : null;
    }
}
