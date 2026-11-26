<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Mail;

use FourStreet\Mail\Attachment;
use FourStreet\Mail\FileMailer;
use FourStreet\Mail\Mailbox;
use FourStreet\Mail\MailDirectory;
use FourStreet\Mail\MailNotSent;
use FourStreet\Mail\Message;
use FourStreet\Mail\SmtpMailer;
use FourStreet\Mail\SmtpServer;
use FourStreet\Mail\SymfonyEmailFactory;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[CoversClass(FileMailer::class)]
#[CoversClass(SmtpMailer::class)]
#[CoversClass(SymfonyEmailFactory::class)]
#[CoversClass(MailNotSent::class)]
#[UsesClass(Attachment::class)]
#[UsesClass(Mailbox::class)]
#[UsesClass(MailDirectory::class)]
#[UsesClass(Message::class)]
#[UsesClass(SmtpServer::class)]
final class MailerTest extends TestCase
{
    private TemporaryDirectory $temporary;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory('4street-posta');
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testTheMimeMessageIsMultipartWithTheSenderAndTheAttachment(): void
    {
        $email = $this->factory()->create(self::message()->withAttachment(new Attachment('prenotazione.ics', 'text/calendar; charset=utf-8; method=PUBLISH', "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n")));
        $raw = $email->toString();

        self::assertSame('4Street <prenotazioni@fourstreet.studio>', $email->getHeaders()->get('From')?->getBodyAsString());
        self::assertSame('Mario Rossi <mario@example.com>', $email->getHeaders()->get('To')?->getBodyAsString());
        self::assertSame('auto-generated', $email->getHeaders()->get('Auto-Submitted')?->getBodyAsString());
        self::assertSame('Wed, 25 Nov 2026 08:00:00 +0100', $email->getHeaders()->get('Date')?->getBodyAsString());
        self::assertStringContainsString('Content-Type: multipart/mixed', $raw);
        self::assertStringContainsString('Content-Type: multipart/alternative', $raw);
        self::assertStringContainsString('Content-Type: text/plain; charset=utf-8', $raw);
        self::assertStringContainsString('Content-Type: text/html; charset=utf-8', $raw);
        self::assertStringContainsString('Content-Type: text/calendar; charset=utf-8; method=PUBLISH;', $raw);
        self::assertStringContainsString('filename=prenotazione.ics', $raw);
    }

    public function testTheFileMailerWritesOneEmlPerMessage(): void
    {
        $mailer = FileMailer::inDirectory(new MailDirectory($this->temporary->path . '/var/mail'), self::sender(), FrozenClock::at('2026-11-25 08:00:00'));

        $mailer->send(self::message());
        $mailer->send(self::message());

        $files = $this->temporary->files('var/mail');
        self::assertCount(2, $files);
        self::assertMatchesRegularExpression('/\A20261125-080000-000000-[0-9a-f]{8}\.eml\z/', $files[0]);
        $raw = (string) file_get_contents($this->temporary->path . '/var/mail/' . $files[0]);
        self::assertStringContainsString('Subject: Conferma la tua email', $raw);
        self::assertStringContainsString('Ciao Mario', quoted_printable_decode($raw));
    }

    public function testTheFileMailerReportsADirectoryItCannotWrite(): void
    {
        $file = $this->temporary->file('occupato', 'non una cartella');
        $mailer = FileMailer::inDirectory(new MailDirectory($file . '/mail'), self::sender(), FrozenClock::at('2026-11-25 08:00:00'));

        $this->expectException(MailNotSent::class);
        $this->expectExceptionMessage('Cartella della posta non scrivibile');

        $mailer->send(self::message());
    }

    public function testTheSmtpMailerHandsTheMessageToTheTransport(): void
    {
        $transport = new class implements TransportInterface {
            /** @var list<RawMessage> */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->sent[] = $message;

                return null;
            }

            public function __toString(): string
            {
                return 'prova://';
            }
        };

        (new SmtpMailer($transport, $this->factory()))->send(self::message());

        self::assertCount(1, $transport->sent);
        self::assertInstanceOf(Email::class, $transport->sent[0]);
        self::assertSame('Conferma la tua email', $transport->sent[0]->getSubject());
    }

    public function testAFailedSendBecomesMailNotSent(): void
    {
        $transport = new class implements TransportInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('Connessione rifiutata.');
            }

            public function __toString(): string
            {
                return 'prova://';
            }
        };

        try {
            (new SmtpMailer($transport, $this->factory()))->send(self::message());
            self::fail('L\'invio doveva fallire.');
        } catch (MailNotSent $error) {
            self::assertSame('Email "Conferma la tua email" non inviata: Connessione rifiutata.', $error->getMessage());
            self::assertInstanceOf(TransportException::class, $error->getPrevious());
        }
    }

    public function testTheSmtpTransportRequiresTlsAwayFromLocalhost(): void
    {
        $hostinger = new SmtpServer('smtp.hostinger.com', 587, 'prenotazioni@fourstreet.studio', 'segreta');
        $local = new SmtpServer('localhost', 1025);

        self::assertFalse($hostinger->implicitTls());
        self::assertTrue($hostinger->requiresTls());
        self::assertTrue((new SmtpServer('smtp.hostinger.com', 465))->implicitTls());
        self::assertFalse($local->requiresTls());
    }

    private function factory(): SymfonyEmailFactory
    {
        return new SymfonyEmailFactory(self::sender(), FrozenClock::at('2026-11-25 08:00:00'));
    }

    private static function sender(): Mailbox
    {
        return new Mailbox('prenotazioni@fourstreet.studio', '4Street');
    }

    private static function message(): Message
    {
        return new Message(new Mailbox('mario@example.com', 'Mario Rossi'), 'Conferma la tua email', "Ciao Mario,\nconferma.\n", '<p>Ciao Mario,</p>');
    }
}
