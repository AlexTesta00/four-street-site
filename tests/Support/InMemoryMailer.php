<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Mail\Mailer;
use FourStreet\Mail\MailNotSent;
use FourStreet\Mail\Message;
use PHPUnit\Framework\Assert;

/**
 * Posta in memoria: i messaggi restano qui, e il test ne legge i link. Con $failing ogni invio fallisce,
 * con $unreachable solo quelli verso gli indirizzi dell'elenco.
 */
final class InMemoryMailer implements Mailer
{
    /** @var list<Message> */
    public array $sent = [];

    public bool $failing = false;

    /** @var list<string> */
    public array $unreachable = [];

    public function send(Message $message): void
    {
        if ($this->failing || in_array($message->to->email, $this->unreachable, true)) {
            throw new MailNotSent('Il server di posta di prova è spento.');
        }
        $this->sent[] = $message;
    }

    /**
     * @param string|null $subject solo quelli con l'oggetto che comincia così
     *
     * @return list<Message> i messaggi mandati a quell'indirizzo, in ordine
     *
     * @phpstan-impure
     */
    public function sentTo(string $email, ?string $subject = null): array
    {
        return array_values(array_filter(
            $this->sent,
            static fn(Message $message): bool => $message->to->email === $email && ($subject === null || str_starts_with($message->subject, $subject)),
        ));
    }

    /**
     * L'ultimo messaggio mandato a quell'indirizzo: il test fallisce se non ce ne sono.
     *
     * @phpstan-impure
     */
    public function lastTo(string $email): Message
    {
        $messages = $this->sentTo($email);
        Assert::assertNotSame([], $messages, sprintf('Nessuna email per %s.', $email));

        return $messages[count($messages) - 1];
    }

    /**
     * Il link del messaggio verso quel percorso, senza l'origine: "/account/verifica/?token=…".
     * Lo cerca nel testo semplice e controlla che lo stesso link ci sia anche nell'HTML.
     */
    public static function link(Message $message, string $path): string
    {
        Assert::assertSame(1, preg_match('#https?://[^/\s]+(' . preg_quote($path, '#') . '[^\s]*)#', $message->text, $matches), sprintf('Nessun link a %s nel testo.', $path));
        $link = $matches[1];
        Assert::assertStringContainsString(htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), (string) $message->html);

        return $link;
    }
}
