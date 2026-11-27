<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Calendar\IcsCalendar;
use FourStreet\Calendar\IcsEvent;
use FourStreet\Calendar\IcsText;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IcsCalendar::class)]
#[CoversClass(IcsEvent::class)]
#[UsesClass(IcsText::class)]
final class IcsCalendarTest extends TestCase
{
    public function testTimedEventInUtc(): void
    {
        $ics = (new IcsCalendar('-//4Street//Inaugurazione//IT'))->withEvent(self::event(allDay: false))->render();

        self::assertSame(
            "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//4Street//Inaugurazione//IT\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:PUBLISH\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:inaugurazione-20261206@4street\r\n"
            . "DTSTAMP:20261125T070000Z\r\n"
            . "DTSTART:20261206T140000Z\r\n"
            . "SUMMARY:Inaugurazione 4Street\r\n"
            . "LOCATION:Via del Bando 22\\, San Marino\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n",
            $ics,
        );
    }

    public function testAllDayEventWhenTheTimeIsNotConfirmed(): void
    {
        $ics = (new IcsCalendar('-//4Street//Inaugurazione//IT'))->withEvent(self::event(allDay: true))->render();

        self::assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261206\r\nDTEND;VALUE=DATE:20261207\r\n", $ics);
        self::assertStringNotContainsString('DTSTART:', $ics);
    }

    public function testAllDayEventUsesTheLocalDateNotTheUtcOne(): void
    {
        // 00:30 a Roma è ancora il giorno prima in UTC: conta il giorno locale.
        $event = self::event(allDay: true, start: '2026-12-06 00:30');
        $ics = (new IcsCalendar('-//4Street//Inaugurazione//IT'))->withEvent($event)->render();

        self::assertStringContainsString('DTSTART;VALUE=DATE:20261206', $ics);
        self::assertStringContainsString('DTEND;VALUE=DATE:20261207', $ics);
    }

    public function testSummerTimeIsConvertedToUtc(): void
    {
        $ics = (new IcsCalendar('-//4Street//Prova//IT'))->withEvent(self::event(allDay: false, start: '2027-07-01 15:00'))->render();

        self::assertStringContainsString("\r\nDTSTART:20270701T130000Z\r\n", $ics);
    }

    public function testEveryLineEndsWithCrlf(): void
    {
        $ics = (new IcsCalendar('-//4Street//Inaugurazione//IT'))->withEvent(self::event(allDay: false))->render();

        self::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        self::assertSame(0, preg_match("/(?<!\r)\n/", $ics), 'Nessun a capo senza CR.');
        self::assertSame(0, preg_match("/\r(?!\n)/", $ics), 'Nessun CR senza a capo.');
    }

    public function testTextValuesAreEscapedAndLongLinesFolded(): void
    {
        $event = new IcsEvent(
            uid: 'prova;1@4street',
            summary: "Festa, musica\ne brindisi",
            location: 'Strada ' . str_repeat('Lunghissima ', 10) . '1; San Marino',
            start: self::rome('2026-12-06 15:00'),
            allDay: false,
            stamp: self::rome('2026-11-25 08:00'),
        );
        $ics = (new IcsCalendar('-//4Street//Inaugurazione//IT'))->withEvent($event)->render();

        self::assertStringContainsString("\r\nUID:prova\\;1@4street\r\n", $ics);
        self::assertStringContainsString("\r\nSUMMARY:Festa\\, musica\\ne brindisi\r\n", $ics);
        self::assertStringContainsString("\r\n ", $ics, 'La riga lunga va spezzata con una continuazione.');
        foreach (explode("\r\n", rtrim($ics, "\r\n")) as $line) {
            self::assertLessThanOrEqual(75, strlen($line));
        }
        $unfolded = str_replace("\r\n ", '', $ics);
        self::assertStringContainsString('LOCATION:Strada ' . str_repeat('Lunghissima ', 10) . '1\; San Marino' . "\r\n", $unfolded);
    }

    public function testAnEventWithItsEndDescriptionAndPage(): void
    {
        $event = new IcsEvent(
            uid: 'prenotazione-42@fourstreet.studio',
            summary: 'Allenamento a 4Street',
            location: 'Via del Bando 22, San Marino',
            start: self::rome('2026-12-07 18:30'),
            allDay: false,
            stamp: self::rome('2026-11-25 08:00'),
            end: self::rome('2026-12-07 19:30'),
            description: 'Posto 3 di 5. Per disdire: https://fourstreet.studio/account/',
            url: 'https://fourstreet.studio/account/',
        );
        $ics = (new IcsCalendar('-//4Street//Prenotazione//IT'))->withEvent($event)->render();

        self::assertStringContainsString(
            "BEGIN:VEVENT\r\n"
            . "UID:prenotazione-42@fourstreet.studio\r\n"
            . "DTSTAMP:20261125T070000Z\r\n"
            . "DTSTART:20261207T173000Z\r\n"
            . "DTEND:20261207T183000Z\r\n"
            . "SUMMARY:Allenamento a 4Street\r\n"
            . "LOCATION:Via del Bando 22\\, San Marino\r\n"
            . "DESCRIPTION:Posto 3 di 5. Per disdire: https://fourstreet.studio/account/\r\n"
            . "URL:https://fourstreet.studio/account/\r\n"
            . "END:VEVENT\r\n",
            $ics,
        );
    }

    public function testAnEventEndsAfterItStartsAndLinksOnlyToWebPages(): void
    {
        foreach ([
            static fn(): IcsEvent => new IcsEvent('a', 'b', 'c', self::rome('2026-12-07 18:30'), false, self::rome('2026-11-25 08:00'), end: self::rome('2026-12-07 18:30')),
            static fn(): IcsEvent => new IcsEvent('a', 'b', 'c', self::rome('2026-12-07 18:30'), true, self::rome('2026-11-25 08:00'), end: self::rome('2026-12-07 19:30')),
            static fn(): IcsEvent => new IcsEvent('a', 'b', 'c', self::rome('2026-12-07 18:30'), false, self::rome('2026-11-25 08:00'), url: 'javascript:alert(1)'),
            static fn(): IcsEvent => new IcsEvent('a', 'b', 'c', self::rome('2026-12-07 18:30'), false, self::rome('2026-11-25 08:00'), url: "https://fourstreet.studio/\r\nX-EVIL:1"),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('L\'evento doveva essere rifiutato.');
            } catch (InvalidArgumentException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testSeveralEventsAndImmutability(): void
    {
        $empty = new IcsCalendar('-//4Street//Prova//IT');
        $one = $empty->withEvent(self::event(allDay: false));
        $two = $one->withEvent(self::event(allDay: true));

        self::assertSame(0, substr_count($empty->render(), 'BEGIN:VEVENT'));
        self::assertSame(1, substr_count($one->render(), 'BEGIN:VEVENT'));
        self::assertSame(2, substr_count($two->render(), 'BEGIN:VEVENT'));
        self::assertSame(2, substr_count($two->render(), 'END:VEVENT'));
    }

    private static function event(bool $allDay, string $start = '2026-12-06 15:00'): IcsEvent
    {
        return new IcsEvent(
            uid: 'inaugurazione-20261206@4street',
            summary: 'Inaugurazione 4Street',
            location: 'Via del Bando 22, San Marino',
            start: self::rome($start),
            allDay: $allDay,
            stamp: self::rome('2026-11-25 08:00'),
        );
    }

    private static function rome(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('Europe/Rome'));
    }
}
