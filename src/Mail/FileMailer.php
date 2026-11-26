<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use FourStreet\Clock\Clock;
use LogicException;
use RuntimeException;
use SplFileObject;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

/**
 * Scrive ogni email in un file .eml (il messaggio MIME completo) invece di mandarla: in sviluppo in var/mail/.
 * Il nome del file comincia con data e ora, così l'ultimo arrivato è l'ultimo in ordine alfabetico.
 */
final readonly class FileMailer implements Mailer
{
    public function __construct(
        private string $directory,
        private SymfonyEmailFactory $emails,
        private Clock $clock,
    ) {}

    public static function inDirectory(MailDirectory $directory, Mailbox $sender, Clock $clock): self
    {
        return new self($directory->path, new SymfonyEmailFactory($sender, $clock), $clock);
    }

    public function send(Message $message): void
    {
        $directory = rtrim($this->directory, '/\\');
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new MailNotSent(sprintf('Cartella della posta non scrivibile: %s', $directory));
        }

        $file = sprintf('%s/%s-%s.eml', $directory, $this->clock->now()->format('Ymd-His-u'), bin2hex(random_bytes(4)));
        try {
            $raw = $this->emails->create($message)->toString();
            // "x": il file è sempre nuovo, mai uno già esistente riscritto.
            if ((new SplFileObject($file, 'x'))->fwrite($raw) !== strlen($raw)) {
                throw new RuntimeException(sprintf('Scrittura incompleta di %s.', $file));
            }
        } catch (RuntimeException|LogicException|MimeException $error) {
            throw new MailNotSent(sprintf('Email "%s" non scritta in %s: %s', $message->subject, $directory, $error->getMessage()), 0, $error);
        }
    }
}
