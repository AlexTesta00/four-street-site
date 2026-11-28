<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Account\User;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\CancellationResult;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\GymEmail;
use FourStreet\Booking\RosterEntry;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;

/**
 * La disdetta fatta dal titolare: qualsiasi prenotazione attiva non ancora iniziata, di un cliente registrato
 * o di una persona senza account, anche quando per il cliente la disdetta è già chiusa
 * (cancel_closes_minutes_before vale solo per lui). Il posto si libera subito.
 *
 * Al cliente registrato arriva subito l'email che a disdire è stata la palestra, con lo stato nel database: la disdetta
 * la scrive in attesa, poi GymEmailSender la manda e la segna partita o non partita (se non parte, la pagina Disdette
 * la rimanda). Una persona senza account non ha un indirizzo, e la avvisa il titolare. Lo stesso modulo inviato di nuovo
 * non cambia nulla e non manda email; anche una prenotazione disdetta nel frattempo dal cliente è "già disdetta".
 */
final readonly class StaffCancellation
{
    /**
     * @param GymEmailSender $emails le email della palestra, lo stesso per tutta la richiesta
     */
    public function __construct(
        private BookingRepository $bookings,
        private GymEmailSender $emails,
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
     * Disdice la prenotazione e avvisa il cliente registrato. Esiti: Cancelled (con l'email: Sent, Failed, Pending se
     * l'ha presa nello stesso istante un altro invio, NoAddress per chi non ha l'account), AlreadyCancelled, NotFound
     * (non c'è, oppure la fascia è iniziata), Throttled.
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
        if ($booking->isGuest()) {
            return EmailDelivery::NoAddress;
        }

        // La disdetta di una prenotazione sola non dice un perché, come sempre: l'ha deciso il titolare.
        return match ($this->emails->send([[$booking, null]])->of($booking->id)) {
            GymEmail::Sent => EmailDelivery::Sent,
            GymEmail::Failed => EmailDelivery::Failed,
            // Presa nello stesso istante da "Invia le email rimaste": non è partita da qui, ed è ancora dovuta.
            default => EmailDelivery::Pending,
        };
    }
}
