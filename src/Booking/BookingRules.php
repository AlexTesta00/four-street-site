<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Config\ConfigValues;

/**
 * Le regole della prenotazione (sezione "booking" di config/site.php), validate: ogni errore dice quale
 * chiave correggere. Sono tutte da confermare con il titolare, per questo stanno lì e non nel codice.
 * Una regola resta fissa: una sola prenotazione attiva per cliente al giorno (la garantisce il database).
 */
final readonly class BookingRules
{
    private const array KEYS = [
        'online',
        'capacity',
        'schedule',
        'slot_minutes',
        'days_ahead',
        'closed_dates',
        'booking_closes_minutes_before',
        'cancel_closes_minutes_before',
        'max_upcoming',
        'example_slots',
        'default_slot',
    ];

    /** Al più due mesi di fasce aperte: un numero più grande è quasi sempre un errore di battitura. */
    private const int MAX_DAYS_AHEAD = 62;

    /** Prenotazione e disdetta chiudono al più una settimana prima dell'inizio. */
    private const int MAX_MINUTES_BEFORE = 7 * 24 * 60;

    /**
     * @param bool $online le prenotazioni dal sito sono attive (servono anche database e posta, config/local.php)
     * @param positive-int $capacity posti per fascia
     * @param positive-int $slotMinutes quanto dura una fascia
     * @param int $daysAhead si prenota da oggi fino a questi giorni dopo oggi (0: solo oggi)
     * @param list<LocalDate> $closedDates i giorni di chiusura, senza fasce
     * @param int $bookingClosesMinutesBefore la prenotazione chiude questi minuti prima dell'inizio (0: all'inizio)
     * @param int $cancelClosesMinutesBefore la disdetta chiude questi minuti prima dell'inizio (0: all'inizio)
     * @param positive-int $maxUpcoming prenotazioni future attive per cliente
     * @param ExampleSlotCatalog $examples le fasce di esempio, finché le prenotazioni non sono attive
     */
    public function __construct(
        public bool $online,
        public int $capacity,
        public WeeklySchedule $schedule,
        public int $slotMinutes,
        public int $daysAhead,
        public array $closedDates,
        public int $bookingClosesMinutesBefore,
        public int $cancelClosesMinutesBefore,
        public int $maxUpcoming,
        public ExampleSlotCatalog $examples,
    ) {}

    public static function fromConfig(ConfigValues $booking): self
    {
        $booking->assertKnownKeys(self::KEYS);
        $capacity = $booking->positiveInt('capacity');
        // Una fascia finisce entro la mezzanotte del suo giorno: al più 24 ore.
        $slotMinutes = $booking->intBetween('slot_minutes', 1, 24 * 60);

        return new self(
            online: $booking->flag('online'),
            capacity: $capacity,
            schedule: WeeklySchedule::fromConfig($booking, 'schedule', $slotMinutes),
            slotMinutes: $slotMinutes,
            daysAhead: $booking->intBetween('days_ahead', 0, self::MAX_DAYS_AHEAD),
            closedDates: self::closedDates($booking),
            bookingClosesMinutesBefore: $booking->intBetween('booking_closes_minutes_before', 0, self::MAX_MINUTES_BEFORE),
            cancelClosesMinutesBefore: $booking->intBetween('cancel_closes_minutes_before', 0, self::MAX_MINUTES_BEFORE),
            maxUpcoming: $booking->positiveInt('max_upcoming'),
            examples: ExampleSlotCatalog::fromConfig($booking, $capacity),
        );
    }

    public function isClosedOn(LocalDate $day): bool
    {
        foreach ($this->closedDates as $closed) {
            if ($closed->equals($day)) {
                return true;
            }
        }

        return false;
    }

    /** Fino a quando si disdice, a parole: "fino all'inizio della fascia", "fino a 2 ore prima dell'inizio". */
    public function cancellationDeadline(): string
    {
        return self::deadline($this->cancelClosesMinutesBefore);
    }

    /** Fino a quando si prenota, a parole: "fino all'inizio della fascia", "fino a 30 minuti prima dell'inizio". */
    public function bookingDeadline(): string
    {
        return self::deadline($this->bookingClosesMinutesBefore);
    }

    private static function deadline(int $minutes): string
    {
        return match (true) {
            $minutes === 0 => 'fino all\'inizio della fascia',
            $minutes === 60 => 'fino a un\'ora prima dell\'inizio',
            $minutes % 60 === 0 => sprintf('fino a %d ore prima dell\'inizio', intdiv($minutes, 60)),
            $minutes === 1 => 'fino a un minuto prima dell\'inizio',
            default => sprintf('fino a %d minuti prima dell\'inizio', $minutes),
        };
    }

    /**
     * @return list<LocalDate>
     */
    private static function closedDates(ConfigValues $booking): array
    {
        $value = $booking->raw('closed_dates');
        if (!is_array($value) || !array_is_list($value)) {
            throw $booking->invalid('closed_dates', 'deve essere un elenco di giorni nel formato AAAA-MM-GG, come [\'2026-12-25\'], oppure []', $value);
        }

        $dates = [];
        foreach ($value as $text) {
            $date = is_string($text) ? LocalDate::tryFrom($text) : null;
            if ($date === null) {
                throw $booking->invalid('closed_dates', 'deve avere giorni esistenti nel formato AAAA-MM-GG, come "2026-12-25"', $text);
            }
            $dates[] = $date;
        }

        return $dates;
    }
}
