<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use FourStreet\Clock\Clock;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Trasforma un Message nell'email MIME di Symfony: multipart testo + HTML, allegati, mittente della configurazione.
 */
final readonly class SymfonyEmailFactory
{
    public function __construct(
        private Mailbox $sender,
        private Clock $clock,
    ) {}

    public function create(Message $message): Email
    {
        $email = (new Email())
            ->from(new Address($this->sender->email, $this->sender->name))
            ->to(new Address($message->to->email, $message->to->name))
            ->subject($message->subject)
            ->date($this->clock->now())
            ->text($message->text);
        if ($message->html !== null) {
            $email->html($message->html);
        }
        foreach ($message->attachments as $attachment) {
            $email->attach($attachment->content, $attachment->filename, $attachment->contentType);
        }

        // Email automatiche: chi le riceve non deve rispondere con un "fuori ufficio".
        $email->getHeaders()
            ->addTextHeader('Auto-Submitted', 'auto-generated')
            ->addTextHeader('X-Auto-Response-Suppress', 'All');

        return $email;
    }
}
