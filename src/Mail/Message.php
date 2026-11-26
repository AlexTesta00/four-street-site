<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use InvalidArgumentException;

/**
 * Un'email da mandare: destinatario, oggetto, testo semplice, HTML e allegati facoltativi.
 * Il mittente non c'è: lo decide la configurazione del Mailer. Il testo semplice c'è sempre,
 * perché ogni email si deve leggere anche senza HTML.
 */
final readonly class Message
{
    /**
     * @param list<Attachment> $attachments
     */
    public function __construct(
        public Mailbox $to,
        public string $subject,
        public string $text,
        public ?string $html = null,
        public array $attachments = [],
    ) {
        if (trim($subject) === '' || preg_match('/[\x00-\x1F\x7F]/', $subject) === 1) {
            throw new InvalidArgumentException('L\'oggetto di un\'email è un testo su una riga.');
        }
        if (trim($text) === '') {
            throw new InvalidArgumentException('Un\'email ha sempre il testo semplice.');
        }
    }

    /** Lo stesso messaggio con un allegato in più. */
    public function withAttachment(Attachment $attachment): self
    {
        return new self($this->to, $this->subject, $this->text, $this->html, [...$this->attachments, $attachment]);
    }
}
