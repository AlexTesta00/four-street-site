<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use FourStreet\Account\User;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;
use SensitiveParameter;

/**
 * La prenotazione di una fascia da parte di un cliente: check() dice come andrebbe (la pagina di riepilogo),
 * book() prenota davvero e manda subito la conferma.
 *
 * L'ordine dei controlli è quello che serve a chi legge la risposta: prima se la fascia esiste e si
 * prenota ancora (calendario, senza database), poi le regole del cliente (una al giorno, al massimo
 * N future) e per ultimo i posti. In book() le regole del cliente e i posti li ricontrolla il database
 * in un passo solo, così due richieste insieme non li aggirano.
 */
final readonly class SlotBooking
{
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private BookingNotifier $notifier,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /**
     * Come andrebbe la prenotazione adesso, senza prenotare: Available con il posto che toccherebbe,
     * oppure il motivo per cui non si può.
     */
    public function check(User $customer, LocalDate $date, SlotTime $time): BookingResult
    {
        $now = $this->clock->now();
        $slot = $this->locate($date, $time, $now);
        if ($slot instanceof BookingResult) {
            return $slot;
        }

        $existing = $this->bookings->activeOn($customer->id, $date);
        if ($existing !== null) {
            return BookingResult::alreadyBooked($slot, $existing);
        }
        if ($this->bookings->countUpcoming($customer->id, $now) >= $this->calendar->rules()->maxUpcoming) {
            return BookingResult::tooManyUpcoming($slot);
        }

        // locate() ha già visto che la fascia si prenota: resta da vedere se c'è posto.
        $seats = new Slot($time->value, $this->calendar->rules()->capacity, $this->bookings->occupancy($date, $date)->seatsOf($date, $time));

        return $seats->nextSeat() === null ? BookingResult::full($slot) : BookingResult::available($slot, $seats);
    }

    /**
     * Prenota la fascia per il cliente e gli manda la conferma con l'evento per il calendario.
     * Ogni invio conta come tentativo, per l'account e per l'indirizzo IP, prima di ogni controllo.
     *
     * @param string $ip l'indirizzo della richiesta, per il limite dei tentativi (non si salva)
     */
    public function book(User $customer, LocalDate $date, SlotTime $time, #[SensitiveParameter] string $ip): BookingResult
    {
        $limit = BookingThrottle::hit($this->limiter, $customer, $ip);
        if (!$limit->allowed) {
            return BookingResult::throttled($limit->retryAfter);
        }

        $now = $this->clock->now();
        $slot = $this->locate($date, $time, $now);
        if ($slot instanceof BookingResult) {
            return $slot;
        }

        $rules = $this->calendar->rules();
        $result = $this->bookings->place($customer->id, $slot, $rules->capacity, $rules->maxUpcoming, $now, CreatedBy::Customer);
        if ($result->status !== BookingStatus::Booked) {
            return $result;
        }

        return $result->withEmail($this->notifier->booked($customer, $result->booking(), $now));
    }

    /**
     * La fascia, se esiste e si prenota ancora; altrimenti l'esito che lo dice.
     */
    private function locate(LocalDate $date, SlotTime $time, DateTimeImmutable $now): ScheduledSlot|BookingResult
    {
        $slot = $this->calendar->slot($date, $time);
        // Oltre gli ultimi giorni prenotabili la fascia non è ancora pubblicata: per chi chiede non c'è.
        if ($slot === null || $this->calendar->isBeyondBookableDays($date, $now)) {
            return BookingResult::notFound();
        }

        return $this->calendar->canBook($slot, $now) ? $slot : BookingResult::closed($slot);
    }
}
