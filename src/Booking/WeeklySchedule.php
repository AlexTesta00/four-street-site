<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Config\ConfigValues;

/**
 * L'orario della settimana: per ogni giorno gli orari d'inizio delle fasce, dal più presto.
 * Un giorno senza orari è chiuso. Le fasce dello stesso giorno non si sovrappongono e finiscono
 * entro la mezzanotte, così ogni fascia appartiene a un giorno solo.
 */
final readonly class WeeklySchedule
{
    /**
     * @param array<int, list<SlotTime>> $days numero del giorno (Weekday) => orari d'inizio, in ordine
     */
    private function __construct(
        private array $days,
    ) {}

    /**
     * La chiave $key della sezione "booking": tutti e sette i giorni, ognuno con il suo elenco di orari.
     *
     *     'schedule' => ['lunedì' => ['07:00', '18:30'], …, 'domenica' => []]
     *
     * @param positive-int $slotMinutes quanto dura una fascia: due orari più vicini si sovrappongono
     */
    public static function fromConfig(ConfigValues $booking, string $key, int $slotMinutes): self
    {
        $value = $booking->raw($key);
        if (!is_array($value)) {
            throw $booking->invalid($key, 'deve essere un elenco giorno => orari, con tutti e sette i giorni', $value);
        }

        $days = [];
        foreach ($value as $name => $times) {
            $day = Weekday::fromName((string) $name);
            if ($day === null) {
                throw $booking->invalid($key, 'ha un giorno sconosciuto: i giorni sono lunedì, martedì, mercoledì, giovedì, venerdì, sabato e domenica', (string) $name);
            }
            if (isset($days[$day->value])) {
                throw $booking->invalid($key, sprintf('ha %s due volte', $day->label()), (string) $name);
            }
            $days[$day->value] = self::times($booking, $key . '.' . $day->label(), $times, $slotMinutes);
        }

        foreach (Weekday::cases() as $day) {
            if (!isset($days[$day->value])) {
                throw $booking->invalid($key, sprintf('deve elencare tutti e sette i giorni: manca %s (scrivi [] se quel giorno è chiuso)', $day->label()), implode(', ', array_map(strval(...), array_keys($value))));
            }
        }

        return new self($days);
    }

    /**
     * La settimana tipo salvata dal titolare, già validata quando l'ha scritta: per ogni giorno della settimana
     * (il numero di Weekday) gli orari d'inizio. Un giorno che manca è chiuso.
     *
     * @param array<int, list<SlotTime>> $days
     */
    public static function of(array $days): self
    {
        $week = [];
        foreach (Weekday::cases() as $day) {
            $times = $days[$day->value] ?? [];
            usort($times, static fn(SlotTime $a, SlotTime $b): int => $a->minutes <=> $b->minutes);
            $week[$day->value] = $times;
        }

        return new self($week);
    }

    /**
     * Gli orari d'inizio delle fasce di quel giorno della settimana, dal più presto.
     *
     * @return list<SlotTime>
     */
    public function on(Weekday $day): array
    {
        return $this->days[$day->value] ?? [];
    }

    /**
     * @param positive-int $slotMinutes
     *
     * @return list<SlotTime>
     */
    private static function times(ConfigValues $booking, string $key, mixed $value, int $slotMinutes): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw $booking->invalid($key, 'deve essere un elenco di orari come [\'07:00\', \'18:30\'], oppure [] se il giorno è chiuso', $value);
        }

        $times = [];
        foreach ($value as $text) {
            $time = is_string($text) ? SlotTime::tryFrom($text) : null;
            if ($time === null) {
                throw $booking->invalid($key, 'deve avere orari a 24 ore nel formato HH:MM, come "18:30"', is_int($text) ? (string) $text : $text);
            }
            $times[] = $time;
        }
        // In ordine dal più presto, anche se nel file sono scritti in un altro ordine.
        usort($times, static fn(SlotTime $a, SlotTime $b): int => $a->minutes <=> $b->minutes);

        $previous = null;
        foreach ($times as $time) {
            if ($previous !== null && $time->minutes - $previous->minutes < $slotMinutes) {
                throw $booking->invalid($key, $previous->equals($time)
                    ? 'ha la stessa fascia due volte'
                    : sprintf('ha due fasce che si sovrappongono, alle %s e alle %s: ogni fascia dura %d minuti (slot_minutes)', $previous, $time, $slotMinutes), $time->value);
            }
            $previous = $time;
        }
        if ($previous !== null && $previous->minutes + $slotMinutes > 24 * 60) {
            throw $booking->invalid($key, sprintf('ha una fascia che finisce dopo la mezzanotte: ogni fascia dura %d minuti (slot_minutes)', $slotMinutes), $previous->value);
        }

        return $times;
    }
}
