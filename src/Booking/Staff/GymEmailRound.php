<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\GymEmail;

/**
 * Com'è andato un giro di email della palestra (GymEmailSender::send()): le email provate, una per prenotazione, e
 * perché ci si è fermati prima della fine, se è successo.
 */
final readonly class GymEmailRound
{
    /**
     * @param array<int, GymEmail> $results per numero di prenotazione, le email provate in questo giro: Sent, Failed,
     *                                      oppure Sending se l'ha presa nello stesso istante un altro invio (la manda lui).
     *                                      Non c'è chi non è stato provato, né chi non ha più l'account
     * @param GymEmailStop|null $stopped perché ci si è fermati prima della fine: le email dopo non sono state provate
     */
    public function __construct(
        public array $results,
        public ?GymEmailStop $stopped = null,
    ) {}

    /** Com'è andata l'email di quella prenotazione in questo giro; null se non è stata provata (resta com'era). */
    public function of(int $bookingId): ?GymEmail
    {
        return $this->results[$bookingId] ?? null;
    }

    /** Quante email sono partite in questo giro. */
    public function sent(): int
    {
        return count(array_filter($this->results, static fn(GymEmail $email): bool => $email === GymEmail::Sent));
    }
}
