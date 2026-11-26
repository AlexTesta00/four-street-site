<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use FourStreet\Error\ExceptionReporter;

/**
 * Le email che partono dopo la risposta: queue() le mette da parte, flush() le manda quando la pagina
 * è già arrivata al browser (Application::finish(), chiamato da index.php). Così la pagina non aspetta
 * il server di posta e resta la stessa qualunque cosa succeda all'invio: non rivela se un'email è partita.
 * Un invio fallito, a quel punto, non ha più una pagina a cui arrivare: si registra e si passa al successivo.
 * Vive quanto una richiesta, come l'Application che la svuota.
 */
final class Outbox
{
    /** @var list<Message> */
    private array $messages = [];

    public function __construct(
        private readonly Mailer $mailer,
        private readonly ExceptionReporter $reporter,
    ) {}

    public function queue(Message $message): void
    {
        $this->messages[] = $message;
    }

    /** Manda le email in coda, nell'ordine in cui sono arrivate. Ognuna parte al massimo una volta. */
    public function flush(): void
    {
        $messages = $this->messages;
        $this->messages = [];
        foreach ($messages as $message) {
            try {
                $this->mailer->send($message);
            } catch (MailNotSent $error) {
                $this->reporter->report($error);
            }
        }
    }
}
