<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\User;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;
use SensitiveParameter;

/**
 * La disdetta di una prenotazione da parte del cliente: solo una sua, attiva, e finché la disdetta
 * è aperta (fino a cancel_closes_minutes_before dall'inizio). Il posto si libera subito e al cliente
 * arriva l'email della disdetta. Lo stesso modulo inviato di nuovo non cambia nulla e non manda email.
 */
final readonly class BookingCancellation
{
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private BookingNotifier $notifier,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /** La prenotazione che il cliente può disdire adesso, oppure null: per la pagina di conferma. */
    public function cancellable(User $customer, int $bookingId): ?Booking
    {
        $booking = $this->bookings->find($bookingId);

        return $booking !== null && $this->canCancel($customer, $booking) ? $booking : null;
    }

    /**
     * Disdice la prenotazione e manda l'email. Ogni invio conta come tentativo, per l'account e per l'IP.
     *
     * @param string $ip l'indirizzo della richiesta, per il limite dei tentativi (non si salva)
     */
    public function cancel(User $customer, int $bookingId, #[SensitiveParameter] string $ip): CancellationResult
    {
        $limit = BookingThrottle::hit($this->limiter, $customer, $ip);
        if (!$limit->allowed) {
            return CancellationResult::throttled($limit->retryAfter);
        }

        $booking = $this->bookings->find($bookingId);
        if ($booking === null || !$booking->belongsTo($customer->id)) {
            return CancellationResult::notFound();
        }
        if (!$booking->isActive()) {
            return $this->repeated($booking);
        }
        if (!$this->canCancel($customer, $booking)) {
            return CancellationResult::notFound();
        }
        // La disdetta vale solo se la prenotazione era ancora attiva: con due invii insieme, uno solo la disdice.
        if (!$this->bookings->cancel($booking->id, $this->clock->now(), CancelledBy::Customer)) {
            return $this->repeated($this->bookings->find($booking->id) ?? $booking);
        }

        return CancellationResult::cancelled($booking, $this->notifier->cancelled($customer, $booking));
    }

    private function canCancel(User $customer, Booking $booking): bool
    {
        return $booking->belongsTo($customer->id)
            && $booking->isActive()
            && $this->calendar->canCancel($booking->startsAt, $this->clock->now());
    }

    /** Una prenotazione già disdetta: dal cliente stesso è un invio ripetuto, dalla palestra non c'è più nulla da fare. */
    private function repeated(Booking $booking): CancellationResult
    {
        return $booking->cancelledBy === CancelledBy::Customer
            ? CancellationResult::alreadyCancelled($booking)
            : CancellationResult::notFound();
    }
}
