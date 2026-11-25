<?php

declare(strict_types=1);

namespace FourStreet\Calendar;

use DateTimeImmutable;

/**
 * Un evento del calendario (VEVENT).
 */
final readonly class IcsEvent
{
    /**
     * @param bool $allDay evento di tutto il giorno (orario non confermato): conta solo la data locale di $start
     * @param DateTimeImmutable $stamp istante in cui l'evento è stato generato (DTSTAMP)
     */
    public function __construct(
        public string $uid,
        public string $summary,
        public string $location,
        public DateTimeImmutable $start,
        public bool $allDay,
        public DateTimeImmutable $stamp,
    ) {}
}
