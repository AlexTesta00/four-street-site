<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use DateTimeImmutable;
use FourStreet\Booking\ScheduledSlot;

/**
 * A che punto è una fascia rispetto ad adesso. Il titolare aggiunge e disdice solo prima dell'inizio:
 * dopo, la fascia si guarda soltanto.
 */
enum SlotPhase
{
    /** Non è ancora iniziata. */
    case Upcoming;

    /** È in corso. */
    case InProgress;

    /** È finita. */
    case Over;

    /** I confronti si fanno sugli istanti: il cambio dell'ora non sposta nulla. */
    public static function of(ScheduledSlot $slot, DateTimeImmutable $now): self
    {
        return match (true) {
            $now < $slot->startsAt => self::Upcoming,
            $now < $slot->endsAt => self::InProgress,
            default => self::Over,
        };
    }
}
