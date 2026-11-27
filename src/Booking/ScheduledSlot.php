<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;

/**
 * Una fascia del calendario: un giorno e un orario d'inizio, ora locale del sito, con i due istanti
 * in cui comincia e finisce. Il giorno e l'orario la identificano; gli istanti nascono dal fuso del sito.
 */
final readonly class ScheduledSlot
{
    /**
     * @param DateTimeImmutable $startsAt l'inizio, nel fuso del sito
     * @param DateTimeImmutable $endsAt la fine (inizio più la durata, in tempo reale), nel fuso del sito
     */
    public function __construct(
        public LocalDate $date,
        public SlotTime $time,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}

    /** "2026-12-07 18:30": la chiave della fascia, la stessa per il calendario e per il database. */
    public function key(): string
    {
        return self::keyOf($this->date, $this->time);
    }

    public static function keyOf(LocalDate $date, SlotTime $time): string
    {
        return $date->value . ' ' . $time->value;
    }

    public function is(LocalDate $date, SlotTime $time): bool
    {
        return $this->date->equals($date) && $this->time->equals($time);
    }
}
