<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use FourStreet\Account\User;
use FourStreet\Error\ExceptionReporter;
use FourStreet\Mail\Mailer;
use FourStreet\Mail\MailNotSent;
use FourStreet\Mail\Message;

/**
 * Manda subito le email delle prenotazioni (non dopo la risposta: la pagina deve dire se sono partite).
 * La prenotazione vale comunque: un invio fallito si registra nel log e diventa EmailDelivery::Failed,
 * mai un errore 500.
 */
final readonly class BookingNotifier
{
    public function __construct(
        private BookingEmails $emails,
        private Mailer $mailer,
        private ExceptionReporter $reporter,
    ) {}

    public function booked(User $customer, Booking $booking, DateTimeImmutable $now): EmailDelivery
    {
        return $this->send($this->emails->confirmation($customer, $booking, $now));
    }

    public function cancelled(User $customer, Booking $booking): EmailDelivery
    {
        return $this->send($this->emails->cancellation($customer, $booking));
    }

    /**
     * La disdetta fatta dalla palestra, con un testo suo.
     *
     * @param string|null $reason perché, in una frase, quando la disdetta viene da un cambio d'orario:
     *                            "Quel giorno la palestra resta chiusa."
     */
    public function cancelledByGym(User $customer, Booking $booking, ?string $reason = null): EmailDelivery
    {
        return $this->send($this->emails->cancellationByGym($customer, $booking, $reason));
    }

    private function send(Message $message): EmailDelivery
    {
        try {
            $this->mailer->send($message);
        } catch (MailNotSent $error) {
            $this->reporter->report($error);

            return EmailDelivery::Failed;
        }

        return EmailDelivery::Sent;
    }
}
