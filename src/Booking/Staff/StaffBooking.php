<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use DateTimeImmutable;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\User;
use FourStreet\Account\UserRepository;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotTime;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;

/**
 * La prenotazione inserita dal titolare, dal pannello: per un cliente registrato (trovato per email, con l'email
 * confermata) oppure per una persona senza account, con il solo nome. Le regole non sono quelle del cliente
 * (SlotBooking):
 *
 * - qualsiasi fascia che esiste nel calendario e non è ancora iniziata, anche oltre days_ahead e senza
 *   booking_closes_minutes_before; mai prima dell'inaugurazione né nei giorni chiusi (lì le fasce non esistono),
 *   e non oltre la portata del pannello (BookingCalendar::isWithinReach());
 * - i posti valgono sempre, anche per il titolare: lo stesso place() del cliente, lo stesso vincolo del database;
 * - per un cliente registrato resta una prenotazione attiva al giorno (il vincolo del database), ma non il massimo
 *   di prenotazioni future;
 * - per una persona senza account lo stesso nome nella stessa fascia è la stessa persona: è già inserita.
 *
 * Al cliente registrato arriva subito la stessa conferma con l'evento .ics, come se avesse prenotato lui, e la
 * prenotazione compare nel suo account. Ogni invio conta per il limite dei tentativi del titolare prima di ogni
 * controllo, compresa la ricerca dell'email: a limite superato un'email nota e una sconosciuta hanno la stessa
 * risposta, e il pannello non serve a scoprire chi ha un account.
 */
final readonly class StaffBooking
{
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private UserRepository $users,
        private BookingNotifier $notifier,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /**
     * Come andrebbe adesso, senza prenotare (il modulo): Available con il posto che toccherebbe, oppure
     * NotFound (la fascia non c'è), Closed (è iniziata) o Full.
     */
    public function check(LocalDate $date, SlotTime $time): BookingResult
    {
        $slot = $this->locate($date, $time, $this->clock->now());
        if ($slot instanceof BookingResult) {
            return $slot;
        }

        $seats = new Slot($time->value, $this->calendar->rules()->capacity, $this->bookings->occupancy($date, $date)->seatsOf($date, $time));

        return $seats->nextSeat() === null ? BookingResult::full($slot) : BookingResult::available($slot, $seats);
    }

    /**
     * Prenota per il cliente registrato con quell'email e gli manda la conferma. Esiti: Booked (con l'email),
     * AlreadyBooked (ha già una prenotazione quel giorno: la stessa fascia, se è lo stesso modulo inviato due volte),
     * UnknownCustomer (nessun account confermato con quell'email), Full, Closed, NotFound, Throttled.
     */
    public function bookCustomer(User $staff, EmailAddress $email, LocalDate $date, SlotTime $time): BookingResult
    {
        $now = $this->clock->now();
        $slot = $this->admit($staff, $date, $time, $now);
        if ($slot instanceof BookingResult) {
            return $slot;
        }

        // Solo un account con l'email confermata: chiunque può registrare un indirizzo non suo.
        $customer = $this->users->findByEmail($email);
        if ($customer === null || !$customer->isVerified()) {
            return BookingResult::unknownCustomer($slot);
        }

        $result = $this->bookings->place($customer->id, $slot, $this->calendar->rules()->capacity, null, $now, CreatedBy::Admin);
        if ($result->status !== BookingStatus::Booked) {
            return $result;
        }

        return $result->withEmail($this->notifier->booked($customer, $result->booking(), $now));
    }

    /**
     * Prenota per una persona senza account: nessuna email, la avvisa il titolare. Esiti: Booked (EmailDelivery::NoAddress),
     * AlreadyBooked (lo stesso nome è già in questa fascia), Full, Closed, NotFound, Throttled.
     */
    public function bookGuest(User $staff, FullName $guest, LocalDate $date, SlotTime $time): BookingResult
    {
        $now = $this->clock->now();
        $slot = $this->admit($staff, $date, $time, $now);
        if ($slot instanceof BookingResult) {
            return $slot;
        }

        $result = $this->bookings->placeGuest($guest, $slot, $this->calendar->rules()->capacity, $staff->id, $now);

        return $result->status === BookingStatus::Booked ? $result->withEmail(EmailDelivery::NoAddress) : $result;
    }

    /** Il limite dei tentativi (prima di ogni controllo), poi la fascia: quella da prenotare, oppure l'esito che dice perché no. */
    private function admit(User $staff, LocalDate $date, SlotTime $time, DateTimeImmutable $now): ScheduledSlot|BookingResult
    {
        $limit = StaffThrottle::hit($this->limiter, $staff);
        if (!$limit->allowed) {
            return BookingResult::throttled($limit->retryAfter);
        }

        return $this->locate($date, $time, $now);
    }

    /**
     * La fascia, se esiste e non è ancora iniziata; altrimenti l'esito che lo dice. Il calendario non ha fasce
     * prima dell'inaugurazione né nei giorni chiusi; i giorni prenotabili e la chiusura anticipata valgono solo
     * per i clienti.
     */
    private function locate(LocalDate $date, SlotTime $time, DateTimeImmutable $now): ScheduledSlot|BookingResult
    {
        $slot = $this->calendar->isWithinReach($date, $now) ? $this->calendar->slot($date, $time) : null;
        if ($slot === null) {
            return BookingResult::notFound();
        }

        return $now < $slot->startsAt ? $slot : BookingResult::closed($slot);
    }
}
