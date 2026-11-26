<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Mail;

use FourStreet\Mail\Attachment;
use FourStreet\Mail\Mailbox;
use FourStreet\Mail\Message;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
#[CoversClass(Mailbox::class)]
#[CoversClass(Attachment::class)]
final class MessageTest extends TestCase
{
    public function testAMessageWithAnAttachment(): void
    {
        $message = new Message(new Mailbox('mario@example.com', 'Mario Rossi'), 'Prenotazione', "Ciao,\na presto.", '<p>Ciao</p>');
        $withIcs = $message->withAttachment(new Attachment('prenotazione.ics', 'text/calendar; charset=utf-8; method=PUBLISH', "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n"));

        self::assertSame([], $message->attachments);
        self::assertCount(1, $withIcs->attachments);
        self::assertSame('prenotazione.ics', $withIcs->attachments[0]->filename);
        self::assertSame('Prenotazione', $withIcs->subject);
        self::assertSame('<p>Ciao</p>', $withIcs->html);
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidParts(): iterable
    {
        yield 'indirizzo non valido' => [static fn(): Mailbox => new Mailbox('mario')];
        yield 'nome su due righe' => [static fn(): Mailbox => new Mailbox('mario@example.com', "Mario\r\nBcc: x@y.z")];
        yield 'oggetto vuoto' => [static fn(): Message => new Message(new Mailbox('a@b.it'), ' ', 'testo')];
        yield 'oggetto su due righe' => [static fn(): Message => new Message(new Mailbox('a@b.it'), "Ciao\nBcc: x@y.z", 'testo')];
        yield 'senza testo semplice' => [static fn(): Message => new Message(new Mailbox('a@b.it'), 'Ciao', ' ', '<p>solo HTML</p>')];
        yield 'nome di file con un percorso' => [static fn(): Attachment => new Attachment('../segreto.ics', 'text/calendar', '')];
        yield 'tipo non valido' => [static fn(): Attachment => new Attachment('a.ics', "text/calendar\r\nX: y", '')];
    }

    /**
     * @param callable(): mixed $create
     */
    #[DataProvider('invalidParts')]
    public function testRejectsPartsThatCouldBreakTheMessage(callable $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }
}
