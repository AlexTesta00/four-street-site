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
        return $this->deliver($this->emails->confirmation($customer, $booking, $now));
    }

    public function cancelled(User $customer, Booking $booking): EmailDelivery
    {
        return $this->deliver($this->emails->cancellation($customer, $booking));
    }

    /**
     * Manda un'email già pronta. La disdetta fatta dalla palestra (BookingEmails::cancellationByGym()) passa da qui:
     * Staff\GymEmailSender la prepara prima di prendere la riga, così fra la presa e l'esito c'è soltanto l'invio.
     */
    public function deliver(Message $message): EmailDelivery
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
