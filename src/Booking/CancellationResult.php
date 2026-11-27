<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use LogicException;

/**
 * Com'è andata la disdetta di una prenotazione.
 */
final readonly class CancellationResult
{
    /**
     * @param int $retryAfter secondi di attesa, solo per Throttled
     */
    private function __construct(
        public CancellationStatus $status,
        private ?Booking $booking = null,
        private ?EmailDelivery $email = null,
        public int $retryAfter = 0,
    ) {}

    /**
     * @param Booking $booking la prenotazione disdetta, così com'era prima: giorno, orario, posto
     * @param EmailDelivery $email l'avviso alla persona (NoAddress se non ha l'account)
     */
    public static function cancelled(Booking $booking, EmailDelivery $email): self
    {
        return new self(CancellationStatus::Cancelled, $booking, $email);
    }

    /** @param Booking $booking la prenotazione già disdetta: per il cliente da lui stesso, per il titolare da chiunque */
    public static function alreadyCancelled(Booking $booking): self
    {
        return new self(CancellationStatus::AlreadyCancelled, $booking);
    }

    public static function notFound(): self
    {
        return new self(CancellationStatus::NotFound);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(CancellationStatus::Throttled, retryAfter: $retryAfter);
    }

    /** La prenotazione disdetta (Cancelled, AlreadyCancelled). */
    public function booking(): Booking
    {
        return $this->booking ?? throw new LogicException(sprintf('Nessuna prenotazione per l\'esito %s.', $this->status->name));
    }

    /** Com'è andata l'email della disdetta (Cancelled). */
    public function email(): EmailDelivery
    {
        return $this->email ?? throw new LogicException(sprintf('Nessuna email per l\'esito %s.', $this->status->name));
    }
}
