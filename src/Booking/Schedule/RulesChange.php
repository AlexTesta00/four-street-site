<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\BookingRules;
use InvalidArgumentException;

/**
 * Le regole che il titolare cambia dal pannello, con gli stessi limiti di config/site.php (BookingRules).
 * Posti per fascia e durata della fascia non ci sono: restano nel file. Il modulo del pannello controlla i valori
 * e dice cosa correggere; qui un valore fuori dai limiti è un errore di programmazione.
 */
final readonly class RulesChange
{
    /**
     * Al più una prenotazione al giorno, e si prenota al più fino a due mesi dopo: oltre questo numero
     * il limite non limiterebbe più nulla.
     */
    public const int MAX_UPCOMING = BookingRules::MAX_DAYS_AHEAD;

    /**
     * @param bool|null $online le prenotazioni dal sito sono accese (contano solo se lo sono anche nel file); null: resta
     *                          la scelta di prima, perché con booking.online spento nel file il pannello non la mostra
     * @param int $daysAhead si prenota da oggi fino a questi giorni dopo (0: solo oggi)
     * @param int $bookingClosesMinutesBefore la prenotazione chiude questi minuti prima dell'inizio
     * @param int $cancelClosesMinutesBefore la disdetta chiude questi minuti prima dell'inizio
     * @param positive-int $maxUpcoming prenotazioni future attive per cliente
     */
    public function __construct(
        public ?bool $online,
        public int $daysAhead,
        public int $bookingClosesMinutesBefore,
        public int $cancelClosesMinutesBefore,
        public int $maxUpcoming,
    ) {
        self::assertBetween('days_ahead', $daysAhead, 0, BookingRules::MAX_DAYS_AHEAD);
        self::assertBetween('booking_closes_minutes_before', $bookingClosesMinutesBefore, 0, BookingRules::MAX_MINUTES_BEFORE);
        self::assertBetween('cancel_closes_minutes_before', $cancelClosesMinutesBefore, 0, BookingRules::MAX_MINUTES_BEFORE);
        self::assertBetween('max_upcoming', $maxUpcoming, 1, self::MAX_UPCOMING);
    }

    /** Le regole in vigore, come punto di partenza del modulo. Un massimo oltre il limite del pannello si mostra al limite. */
    public static function of(BookingRules $rules): self
    {
        return new self(
            $rules->online,
            $rules->daysAhead,
            $rules->bookingClosesMinutesBefore,
            $rules->cancelClosesMinutesBefore,
            min($rules->maxUpcoming, self::MAX_UPCOMING),
        );
    }

    /**
     * Le regole come le salva il database (booking_settings): chiave => valore testuale. Senza online, la chiave
     * non c'è: resta quella di prima.
     *
     * @return array<string, non-empty-string>
     */
    public function values(): array
    {
        $values = [
            'days_ahead' => (string) $this->daysAhead,
            'booking_closes_minutes_before' => (string) $this->bookingClosesMinutesBefore,
            'cancel_closes_minutes_before' => (string) $this->cancelClosesMinutesBefore,
            'max_upcoming' => (string) $this->maxUpcoming,
        ];

        return $this->online === null ? $values : ['online' => $this->online ? '1' : '0', ...$values];
    }

    private static function assertBetween(string $key, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(sprintf('%s deve essere fra %d e %d: %d.', $key, $min, $max, $value));
        }
    }
}
