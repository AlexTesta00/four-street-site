<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Mail;

use FourStreet\Mail\Mailbox;
use FourStreet\Mail\MailNotSent;
use FourStreet\Mail\Message;
use FourStreet\Mail\Outbox;
use FourStreet\Tests\Support\CollectingReporter;
use FourStreet\Tests\Support\InMemoryMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Outbox::class)]
#[UsesClass(Mailbox::class)]
#[UsesClass(Message::class)]
final class OutboxTest extends TestCase
{
    public function testNothingLeavesBeforeTheFlushAndEachEmailLeavesOnce(): void
    {
        $mailer = new InMemoryMailer();
        $outbox = new Outbox($mailer, new CollectingReporter());

        $outbox->queue(self::message('mario@example.com'));
        $outbox->queue(self::message('anna@example.com'));
        self::assertSame([], $mailer->sent);

        $outbox->flush();
        self::assertSame(['mario@example.com', 'anna@example.com'], self::recipients($mailer));

        $outbox->flush();
        self::assertSame(['mario@example.com', 'anna@example.com'], self::recipients($mailer));
    }

    public function testAFailedEmailIsReportedAndTheOthersStillLeave(): void
    {
        $mailer = new InMemoryMailer();
        $mailer->unreachable = ['spenta@example.com'];
        $reporter = new CollectingReporter();
        $outbox = new Outbox($mailer, $reporter);
        $outbox->queue(self::message('spenta@example.com'));
        $outbox->queue(self::message('anna@example.com'));

        $outbox->flush();

        self::assertSame(['anna@example.com'], self::recipients($mailer));
        self::assertCount(1, $reporter->reported);
        self::assertInstanceOf(MailNotSent::class, $reporter->reported[0]);
    }

    /**
     * @return list<string>
     */
    private static function recipients(InMemoryMailer $mailer): array
    {
        return array_map(static fn(Message $message): string => $message->to->email, $mailer->sent);
    }

    private static function message(string $to): Message
    {
        return new Message(new Mailbox($to), 'Conferma la tua email', "Ciao,\nconferma.\n");
    }
}
