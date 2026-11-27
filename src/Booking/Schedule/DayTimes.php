<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\SlotTime;

/**
 * Gli orari d'inizio delle fasce di un giorno, come li vuole l'orario: in ordine, senza doppioni, senza fasce che si
 * sovrappongono (data la durata, slot_minutes) e finiti entro la mezzanotte, come in config/site.php (WeeklySchedule).
 * In più un tetto di fasce al giorno: il pannello si usa con il dito, e un numero fuori misura è un errore.
 */
final class DayTimes
{
    /** Anche con fasce di un'ora, più di così non stanno in un giorno. */
    public const int MAX_PER_DAY = 24;

    /**
     * Gli orari dal più presto, senza doppioni.
     *
     * @param list<SlotTime> $times
     *
     * @return list<SlotTime>
     */
    public static function sorted(array $times): array
    {
        $unique = [];
        foreach ($times as $time) {
            $unique[$time->value] = $time;
        }
        // "07:00" < "18:30": un orario HH:MM si ordina come testo.
        ksort($unique, SORT_STRING);

        return array_values($unique);
    }

    /**
     * Gli stessi orari, in qualunque ordine.
     *
     * @param list<SlotTime> $a
     * @param list<SlotTime> $b
     */
    public static function same(array $a, array $b): bool
    {
        return self::values($a) === self::values($b);
    }

    /**
     * Quelli di $times che non sono in $others.
     *
     * @param list<SlotTime> $times
     * @param list<SlotTime> $others
     *
     * @return list<SlotTime>
     */
    public static function without(array $times, array $others): array
    {
        $excluded = array_flip(self::values($others));

        return array_values(array_filter($times, static fn(SlotTime $time): bool => !isset($excluded[$time->value])));
    }

    /**
     * Il problema di quegli orari, a parole, oppure null se vanno bene.
     *
     * @param list<SlotTime> $times in ordine, senza doppioni
     * @param positive-int $slotMinutes quanto dura una fascia
     */
    public static function problem(array $times, int $slotMinutes): ?string
    {
        if (count($times) > self::MAX_PER_DAY) {
            return sprintf('Al massimo %d fasce al giorno.', self::MAX_PER_DAY);
        }

        $previous = null;
        foreach ($times as $time) {
            if ($previous !== null && $time->minutes - $previous->minutes < $slotMinutes) {
                return sprintf('Le fasce delle %s e delle %s si sovrappongono: ogni fascia dura %d minuti.', $previous, $time, $slotMinutes);
            }
            $previous = $time;
        }
        if ($previous !== null && $previous->minutes + $slotMinutes > 24 * 60) {
            return sprintf('La fascia delle %s finirebbe dopo la mezzanotte: ogni fascia dura %d minuti.', $previous, $slotMinutes);
        }

        return null;
    }

    /**
     * @param list<SlotTime> $times
     *
     * @return list<string>
     */
    private static function values(array $times): array
    {
        return array_map(static fn(SlotTime $time): string => $time->value, self::sorted($times));
    }
}
