<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Error\ExceptionReporter;
use FourStreet\Mail\Mailer;
use FourStreet\Mail\MailNotSent;
use FourStreet\Mail\Message;
use FourStreet\Mail\Outbox;
use FourStreet\Security\RateLimit\RateLimiter;

/**
 * Manda le email dell'account con due garanzie: un invio fallito non diventa mai un errore 500
 * e nessun indirizzo riceve più di qualche email l'ora. Due modi di mandare:
 * - send(): subito, e la pagina dice se non è partita (la registrazione: la persona deve poter riprovare);
 * - sendLater(): dopo la risposta (Outbox), per le richieste la cui pagina non deve rivelare nulla
 *   (nuovo link di conferma, password dimenticata) e per gli avvisi che non devono farla aspettare.
 */
final readonly class AccountMailer
{
    public function __construct(
        private Mailer $mailer,
        private Outbox $outbox,
        private RateLimiter $limiter,
        private ExceptionReporter $reporter,
    ) {}

    /**
     * Conta un'email in più verso quell'indirizzo e dice se c'è ancora posto.
     * Oltre il limite chi chiama non manda nulla e risponde come se l'avesse mandata.
     */
    public function reserve(EmailAddress $to): bool
    {
        return $this->limiter->hit(AccountThrottle::EmailsPerAddress->limit(), $to->value)->allowed;
    }

    /** Manda subito. Se l'invio fallisce lo registra e risponde Failed: la pagina propone di riprovare. */
    public function send(Message $message): Delivery
    {
        try {
            $this->mailer->send($message);
        } catch (MailNotSent $error) {
            $this->reporter->report($error);

            return Delivery::failed();
        }

        return Delivery::sent();
    }

    /** Manda dopo la risposta. Se l'invio fallisce resta solo nel log: la pagina è già partita, uguale per tutti. */
    public function sendLater(Message $message): void
    {
        $this->outbox->queue($message);
    }
}
