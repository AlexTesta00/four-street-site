<?php

declare(strict_types=1);

namespace FourStreet\Mail;

/**
 * Manda le email del sito. In produzione via SMTP, in sviluppo in file .eml, nei test in memoria.
 */
interface Mailer
{
    /**
     * @throws MailNotSent se il messaggio non è partito: chi chiama lo segnala e dice alla persona cosa fare
     */
    public function send(Message $message): void;
}
