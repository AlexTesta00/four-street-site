<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use InvalidArgumentException;
use LogicException;

/**
 * Una fascia oraria come la vede chi sceglie: l'orario d'inizio, i posti e quali sono già presi,
 * e se si prenota ancora. Chi prenota prende il primo posto libero: con i posti 1, 3 e 4 presi
 * (il 2 è stato disdetto) il prossimo è il 2.
 */
final readonly class Slot
{
    /** Orario a 24 ore con due cifre: "07:00", "18:30". */
    public const string TIME_PATTERN = SlotTime::PATTERN;

    /** Quanti posti sono presi. */
    public int $taken;

    /**
     * @param list<int> $takenSeats i numeri dei posti presi (da 1); dopo una capienza abbassata possono
     *                              superare $capacity, e la fascia resta piena finché non si liberano
     * @param bool $closed la fascia non si prenota più: è iniziata, o sta per iniziare
     */
    public function __construct(
        public string $time,
        public int $capacity,
        public array $takenSeats = [],
        public bool $closed = false,
    ) {
        if (preg_match(self::TIME_PATTERN, $time) !== 1) {
            throw new InvalidArgumentException(sprintf('Orario di fascia non valido: "%s".', $time));
        }
        if ($capacity < 1) {
            throw new InvalidArgumentException(sprintf('Una fascia ha almeno un posto: %d.', $capacity));
        }
        foreach ($takenSeats as $seat) {
            if ($seat < 1) {
                throw new InvalidArgumentException(sprintf('Numero di posto non valido: %d.', $seat));
            }
        }
        if (count(array_unique($takenSeats)) !== count($takenSeats)) {
            throw new InvalidArgumentException(sprintf('Un posto della fascia delle %s è preso due volte.', $time));
        }
        $this->taken = count($takenSeats);
    }

    /** Una fascia di esempio: i primi $taken posti presi, da 0 a $capacity. */
    public static function example(string $time, int $capacity, int $taken): self
    {
        if ($taken < 0 || $taken > $capacity) {
            throw new InvalidArgumentException(sprintf('Posti occupati fuori dai limiti: %d su %d.', $taken, $capacity));
        }

        return new self($time, $capacity, $taken === 0 ? [] : range(1, $taken));
    }

    /** L'orario d'inizio come valore (il costruttore lo ha già validato). */
    public function startTime(): SlotTime
    {
        return SlotTime::tryFrom($this->time) ?? throw new LogicException(sprintf('Orario di fascia non valido: "%s".', $this->time));
    }

    public function isFull(): bool
    {
        return $this->taken >= $this->capacity;
    }

    /** Si può scegliere: non è chiusa e ha almeno un posto libero. */
    public function isBookable(): bool
    {
        return !$this->closed && !$this->isFull();
    }

    public function freeSeats(): int
    {
        return max(0, $this->capacity - $this->taken);
    }

    /** I posti presi da mostrare ("3/5"): mai più della capienza. */
    public function shownTaken(): int
    {
        return min($this->taken, $this->capacity);
    }

    public function isSeatTaken(int $seat): bool
    {
        return in_array($seat, $this->takenSeats, true);
    }

    /**
     * Il posto che prenderebbe chi prenota adesso (il primo libero), oppure null se la fascia è piena o chiusa.
     */
    public function nextSeat(): ?int
    {
        return $this->closed ? null : FirstFreeSeat::among($this->takenSeats, $this->capacity);
    }
}
