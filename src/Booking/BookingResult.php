<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use LogicException;

/**
 * Com'è andata (o come andrebbe) la prenotazione di una fascia. Ogni esito porta solo ciò che gli serve:
 * chiedere il resto è un errore di programmazione.
 */
final readonly class BookingResult
{
    /**
     * @param int $retryAfter secondi di attesa, solo per Throttled
     */
    private function __construct(
        public BookingStatus $status,
        private ?ScheduledSlot $slot = null,
        private ?Booking $booking = null,
        private ?Slot $seats = null,
        public int $retryAfter = 0,
        private ?EmailDelivery $email = null,
    ) {}

    /** La fascia si prenota adesso: $seats sono i suoi posti, con quello che toccherebbe libero. */
    public static function available(ScheduledSlot $slot, Slot $seats): self
    {
        if ($seats->nextSeat() === null) {
            throw new LogicException(sprintf('La fascia %s non ha posti liberi.', $slot->key()));
        }

        return new self(BookingStatus::Available, $slot, seats: $seats);
    }

    public static function booked(ScheduledSlot $slot, Booking $booking): self
    {
        return new self(BookingStatus::Booked, $slot, $booking);
    }

    public static function full(ScheduledSlot $slot): self
    {
        return new self(BookingStatus::Full, $slot);
    }

    /** @param Booking $existing la prenotazione che la persona ha già: quel giorno (cliente) o in quella fascia (senza account) */
    public static function alreadyBooked(ScheduledSlot $slot, Booking $existing): self
    {
        return new self(BookingStatus::AlreadyBooked, $slot, $existing);
    }

    public static function tooManyUpcoming(ScheduledSlot $slot): self
    {
        return new self(BookingStatus::TooManyUpcoming, $slot);
    }

    public static function closed(ScheduledSlot $slot): self
    {
        return new self(BookingStatus::Closed, $slot);
    }

    public static function notFound(): self
    {
        return new self(BookingStatus::NotFound);
    }

    /** @param ScheduledSlot $slot la fascia chiesta, che esiste e si prenota: è l'email a non essere di un cliente */
    public static function unknownCustomer(ScheduledSlot $slot): self
    {
        return new self(BookingStatus::UnknownCustomer, $slot);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(BookingStatus::Throttled, retryAfter: $retryAfter);
    }

    /** La stessa prenotazione, con l'esito dell'email di conferma (NoAddress per una persona senza account). */
    public function withEmail(EmailDelivery $email): self
    {
        if ($this->status !== BookingStatus::Booked) {
            throw new LogicException('Solo una prenotazione fatta ha l\'email di conferma.');
        }

        return new self($this->status, $this->slot, $this->booking, $this->seats, $this->retryAfter, $email);
    }

    /** La fascia chiesta: c'è per tutti gli esiti tranne NotFound e Throttled. */
    public function slot(): ScheduledSlot
    {
        return $this->slot ?? throw new LogicException(sprintf('Nessuna fascia per l\'esito %s.', $this->status->name));
    }

    /** La prenotazione appena fatta (Booked) o quella che il cliente ha già quel giorno (AlreadyBooked). */
    public function booking(): Booking
    {
        return $this->booking ?? throw new LogicException(sprintf('Nessuna prenotazione per l\'esito %s.', $this->status->name));
    }

    /** I posti della fascia, con quelli già presi (Available). */
    public function seats(): Slot
    {
        return $this->seats ?? throw new LogicException(sprintf('Nessun posto per l\'esito %s.', $this->status->name));
    }

    /** Il posto che toccherebbe: il primo libero (Available). */
    public function seat(): int
    {
        return $this->seats()->nextSeat() ?? throw new LogicException('La fascia non ha più posti liberi.');
    }

    /** Com'è andata l'email di conferma (Booked). */
    public function email(): EmailDelivery
    {
        return $this->email ?? throw new LogicException(sprintf('Nessuna email per l\'esito %s.', $this->status->name));
    }

    /** La persona ha già proprio questa fascia: di solito lo stesso modulo inviato due volte. */
    public function isRepeat(): bool
    {
        return $this->status === BookingStatus::AlreadyBooked
            && $this->booking !== null
            && $this->slot !== null
            && $this->booking->isFor($this->slot->date, $this->slot->time);
    }
}
