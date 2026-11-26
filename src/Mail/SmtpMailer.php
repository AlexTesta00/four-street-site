<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use FourStreet\Clock\Clock;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

/**
 * Manda le email con un server SMTP, tramite symfony/mailer. La connessione si apre solo all'invio.
 */
final readonly class SmtpMailer implements Mailer
{
    /** Secondi di attesa massima del server: una pagina non resta appesa a una casella che non risponde. */
    private const float TIMEOUT_SECONDS = 10.0;

    public function __construct(
        private TransportInterface $transport,
        private SymfonyEmailFactory $emails,
    ) {}

    public static function connect(SmtpServer $server, Mailbox $sender, Clock $clock): self
    {
        $transport = new EsmtpTransport($server->host, $server->port, $server->implicitTls());
        // Sulla porta 587 STARTTLS è obbligatorio: senza, la password viaggerebbe in chiaro.
        $transport->setRequireTls(!$server->implicitTls() && $server->requiresTls());
        if ($server->username !== null) {
            $transport->setUsername($server->username);
        }
        if ($server->password !== null) {
            $transport->setPassword($server->password);
        }
        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout(self::TIMEOUT_SECONDS);
        }

        return new self($transport, new SymfonyEmailFactory($sender, $clock));
    }

    public function send(Message $message): void
    {
        try {
            $this->transport->send($this->emails->create($message));
        } catch (MailerException|MimeException $error) {
            throw new MailNotSent(sprintf('Email "%s" non inviata: %s', $message->subject, $error->getMessage()), 0, $error);
        }
    }
}
