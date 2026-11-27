<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Account\User;
use FourStreet\Account\UserRepository;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\CancellationResult;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\RosterEntry;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;

/**
 * La disdetta fatta dal titolare: qualsiasi prenotazione attiva non ancora iniziata, di un cliente registrato
 * o di una persona senza account, anche quando per il cliente la disdetta è già chiusa
 * (cancel_closes_minutes_before vale solo per lui). Il posto si libera subito.
 *
 * Al cliente registrato arriva subito l'email che a disdire è stata la palestra; una persona senza account
 * non ha un indirizzo, e la avvisa il titolare. Lo stesso modulo inviato di nuovo non cambia nulla e non
 * manda email; anche una prenotazione disdetta nel frattempo dal cliente è "già disdetta".
 */
final readonly class StaffCancellation
{
    public function __construct(
        private BookingRepository $bookings,
        private UserRepository $users,
        private BookingNotifier $notifier,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /** La prenotazione con la sua persona, se il titolare la può disdire adesso; altrimenti null (la pagina di conferma). */
    public function cancellable(int $bookingId): ?RosterEntry
    {
        $entry = $this->bookings->findEntry($bookingId);

        return $entry !== null && $this->canCancel($entry->booking) ? $entry : null;
    }

    /**
     * Disdice la prenotazione e avvisa il cliente registrato. Esiti: Cancelled (con l'email, o NoAddress per chi
     * non ha l'account), AlreadyCancelled, NotFound (non c'è, oppure la fascia è iniziata), Throttled.
     */
    public function cancel(User $staff, int $bookingId): CancellationResult
    {
        $limit = StaffThrottle::hit($this->limiter, $staff);
        if (!$limit->allowed) {
            return CancellationResult::throttled($limit->retryAfter);
        }

        $booking = $this->bookings->find($bookingId);
        if ($booking === null) {
            return CancellationResult::notFound();
        }
        if (!$booking->isActive()) {
            return CancellationResult::alreadyCancelled($booking);
        }
        if (!$this->canCancel($booking)) {
            return CancellationResult::notFound();
        }
        // La disdetta vale solo se la prenotazione era ancora attiva: con due invii insieme, uno solo la disdice.
        if (!$this->bookings->cancel($booking->id, $this->clock->now(), CancelledBy::Admin)) {
            return CancellationResult::alreadyCancelled($this->bookings->find($booking->id) ?? $booking);
        }

        return CancellationResult::cancelled($booking, $this->notify($booking));
    }

    /** Fino all'inizio della fascia, qualunque sia cancel_closes_minutes_before. */
    private function canCancel(Booking $booking): bool
    {
        return $booking->isActive() && $this->clock->now() < $booking->startsAt;
    }

    private function notify(Booking $booking): EmailDelivery
    {
        $customer = $booking->userId === null ? null : $this->users->findById($booking->userId);

        return $customer === null ? EmailDelivery::NoAddress : $this->notifier->cancelledByGym($customer, $booking);
    }
}
