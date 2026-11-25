<?php

declare(strict_types=1);

namespace FourStreet\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * File .ics con uno o più eventi: righe CRLF, orari in UTC, testi con escape e righe lunghe spezzate.
 */
final readonly class IcsCalendar
{
    private const string UTC_FORMAT = 'Ymd\THis\Z';
    private const string DATE_FORMAT = 'Ymd';

    /**
     * @param list<IcsEvent> $events
     */
    public function __construct(
        private string $productId,
        private array $events = [],
    ) {}

    public function withEvent(IcsEvent $event): self
    {
        return new self($this->productId, [...$this->events, $event]);
    }

    public function render(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . IcsText::escape($this->productId),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];
        foreach ($this->events as $event) {
            $lines = [...$lines, ...self::eventLines($event)];
        }
        $lines[] = 'END:VCALENDAR';

        $folded = [];
        foreach ($lines as $line) {
            $folded = [...$folded, ...IcsText::fold($line)];
        }

        return implode("\r\n", $folded) . "\r\n";
    }

    /**
     * @return list<string>
     */
    private static function eventLines(IcsEvent $event): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . IcsText::escape($event->uid),
            'DTSTAMP:' . self::utc($event->stamp),
        ];

        if ($event->allDay) {
            // Orario non confermato: evento di tutto il giorno, nel giorno locale dell'inaugurazione.
            $lines[] = 'DTSTART;VALUE=DATE:' . $event->start->format(self::DATE_FORMAT);
            $lines[] = 'DTEND;VALUE=DATE:' . $event->start->modify('+1 day')->format(self::DATE_FORMAT);
        } else {
            $lines[] = 'DTSTART:' . self::utc($event->start);
        }

        $lines[] = 'SUMMARY:' . IcsText::escape($event->summary);
        $lines[] = 'LOCATION:' . IcsText::escape($event->location);
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private static function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::UTC_FORMAT);
    }
}
