<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Booking\Landing\SlotPreview;
use FourStreet\Config\ConfigValues;
use InvalidArgumentException;

/**
 * Le fasce di esempio di config/site.php (sezione "booking"): mostrano come funziona la prenotazione
 * finché le prenotazioni vere non sono attive (booking.online, oppure il server senza database).
 */
final readonly class ExampleSlotCatalog implements SlotCatalog, SlotPreview
{
    private Slot $default;

    /**
     * @param positive-int $capacity
     * @param non-empty-list<Slot> $slots
     * @param string $defaultTime orario della fascia scelta all'apertura della pagina
     */
    public function __construct(
        private int $capacity,
        private array $slots,
        string $defaultTime,
    ) {
        foreach ($slots as $slot) {
            if ($slot->capacity !== $capacity) {
                throw new InvalidArgumentException(sprintf('La fascia delle %s non ha %d posti.', $slot->time, $capacity));
            }
        }
        $default = self::find($slots, $defaultTime);
        if ($default === null || $default->isFull()) {
            throw new InvalidArgumentException(sprintf('La fascia scelta deve esistere e non essere piena: "%s".', $defaultTime));
        }
        $this->default = $default;
    }

    /**
     * Le chiavi "example_slots" e "default_slot" della sezione booking, con la capienza già letta:
     * ['example_slots' => ['18:30' => 3, …], 'default_slot' => '18:30'].
     *
     * @param positive-int $capacity
     */
    public static function fromConfig(ConfigValues $booking, int $capacity): self
    {
        $slots = self::exampleSlots($booking, $capacity);

        $defaultTime = $booking->text('default_slot');
        $default = self::find($slots, $defaultTime);
        if ($default === null || $default->isFull()) {
            throw $booking->invalid('default_slot', 'deve essere l\'orario di una fascia di esempio non piena', $defaultTime);
        }

        return new self($capacity, $slots, $defaultTime);
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function slots(): array
    {
        return $this->slots;
    }

    /** Una fascia di esempio c'è sempre: la configurazione la vuole non piena. */
    public function defaultSlot(): Slot
    {
        return $this->default;
    }

    /**
     * @param positive-int $capacity
     *
     * @return non-empty-list<Slot>
     */
    private static function exampleSlots(ConfigValues $booking, int $capacity): array
    {
        $value = $booking->raw('example_slots');
        if (!is_array($value) || $value === []) {
            throw $booking->invalid('example_slots', 'deve essere un elenco orario => posti occupati, con almeno una fascia', $value);
        }

        $slots = [];
        foreach ($value as $time => $taken) {
            $time = (string) $time;
            if (preg_match(Slot::TIME_PATTERN, $time) !== 1) {
                throw $booking->invalid('example_slots', 'deve avere orari a 24 ore nel formato HH:MM, come "18:30"', $time);
            }
            if (!is_int($taken) || $taken < 0 || $taken > $capacity) {
                throw $booking->invalid(
                    'example_slots.' . $time,
                    sprintf('deve essere il numero di posti occupati, da 0 a %d', $capacity),
                    is_int($taken) ? (string) $taken : $taken,
                );
            }
            $slots[] = Slot::example($time, $capacity, $taken);
        }

        return $slots;
    }

    /**
     * @param list<Slot> $slots
     */
    private static function find(array $slots, string $time): ?Slot
    {
        foreach ($slots as $slot) {
            if ($slot->time === $time) {
                return $slot;
            }
        }

        return null;
    }
}
