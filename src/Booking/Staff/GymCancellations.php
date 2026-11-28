<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Account\User;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\GymEmail;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;

/**
 * Le disdette fatte dalla palestra, per la pagina Disdette del pannello, senza HTTP: chi ha perso una prenotazione la cui
 * fascia deve ancora cominciare, e com'è andata l'email della palestra (GymEmail). Da lì il titolare sa chi avvisare a
 * mano e manda le email rimaste, con lo stesso GymEmailSender delle modifiche dell'orario: lo stato nel database, il
 * tetto di tempo della richiesta, la regola dei due invii falliti di fila. Così due invii del modulo insieme non mandano
 * mai due volte la stessa email, e un'email partita non si rimanda.
 */
final readonly class GymCancellations
{
    /**
     * @param BookingCalendar $calendar l'orario di adesso: dice il perché delle email rimandate
     * @param GymEmailSender $emails le email della palestra, lo stesso per tutta la richiesta
     */
    public function __construct(
        private BookingCalendar $calendar,
        private BookingRepository $bookings,
        private GymEmailSender $emails,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /** Le disdette della palestra con la fascia ancora da cominciare, per giorno e fascia: una lettura sola del database. */
    public function upcoming(): CancellationSheet
    {
        $now = $this->clock->now();
        $today = $this->calendar->today($now);

        return CancellationSheet::of($today, $this->bookings->upcomingGymCancellations($today, $now));
    }

    /**
     * "Invia le email rimaste": quelle in attesa o non partite delle disdette con la fascia ancora da cominciare, rilette
     * adesso (non quelle che la pagina mostrava: nel frattempo un altro invio può averle mandate). Prima quelle mai
     * provate, poi quelle non partite, ognuna nell'ordine delle fasce: due indirizzi che il server di posta rifiuta ogni
     * volta fermano il giro (due invii falliti di fila), ma non tengono indietro per sempre le altre.
     * Il perché lo dice l'orario di adesso: il giorno chiuso, oppure la fascia che non c'è più; altrimenti nessuno, come
     * per la disdetta di una prenotazione sola. Ogni invio del modulo conta per il limite delle modifiche del titolare,
     * prima di ogni controllo.
     */
    public function resend(User $staff): ResendOutcome
    {
        $limit = StaffThrottle::hitSchedule($this->limiter, $staff);
        if (!$limit->allowed) {
            return ResendOutcome::throttled($limit->retryAfter);
        }

        $now = $this->clock->now();
        $pending = [];
        $failed = [];
        foreach ($this->bookings->upcomingGymCancellations($this->calendar->today($now), $now) as $entry) {
            $booking = $entry->booking;
            if ($booking->gymEmail === GymEmail::Pending) {
                $pending[] = [$booking, $this->reason($booking)];
            } elseif ($booking->gymEmail === GymEmail::Failed) {
                $failed[] = [$booking, $this->reason($booking)];
            }
        }
        if ($pending === [] && $failed === []) {
            return ResendOutcome::nothingToSend();
        }

        return ResendOutcome::sent($this->emails->send([...$pending, ...$failed]));
    }

    /** Perché la palestra ha disdetto, secondo l'orario di adesso; null se la fascia c'è ancora. */
    private function reason(Booking $booking): ?string
    {
        return match (true) {
            $this->calendar->plan($booking->date)->isClosed() => BookingEmails::DAY_CLOSED,
            $this->calendar->slot($booking->date, $booking->time) === null => BookingEmails::slotRemoved($booking->time),
            default => null,
        };
    }
}
