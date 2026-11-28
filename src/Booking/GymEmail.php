<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * L'email della palestra a un cliente registrato quando a disdire la sua prenotazione è la palestra (il titolare), con lo
 * stato nel database (bookings.gym_email). La disdetta la scrive in attesa, nella stessa istruzione e prima di ogni
 * invio; chi la manda prima la prende (in invio), poi la segna partita o non partita (Staff\GymEmailSender).
 * Chi non ha l'account non ha un'email, e quindi nessuno stato: lo avvisa il titolare.
 */
enum GymEmail: string
{
    /** In attesa: è dovuta, e nessun invio l'ha ancora provata (per esempio l'ha lasciata indietro il tetto di tempo). */
    case Pending = 'pending';

    /**
     * In invio: una richiesta l'ha presa e la sta mandando. Se resta così, il processo si è interrotto proprio durante
     * l'invio: non si sa se è partita, e non si rimanda mai (mai due email per la stessa disdetta).
     */
    case Sending = 'sending';

    /** Partita: il server di posta l'ha accettata. Non si rimanda mai. */
    case Sent = 'sent';

    /** Non partita: il server di posta l'ha rifiutata o non ha risposto. Si rimanda dalla pagina Disdette. */
    case Failed = 'failed';

    /** Si manda dal pannello: in attesa, oppure non partita. */
    public function canBeSent(): bool
    {
        return $this === self::Pending || $this === self::Failed;
    }
}
