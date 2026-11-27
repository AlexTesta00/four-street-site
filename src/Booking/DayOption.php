<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Un giorno nella scelta dei giorni, con le sue fasce e la prenotazione che il visitatore ha già quel giorno.
 */
final readonly class DayOption
{
    /**
     * @param list<Slot> $slots le fasce del giorno, con i posti
     * @param Booking|null $mine la prenotazione attiva del visitatore in questo giorno (al più una)
     * @param bool $mineCancellable quella prenotazione si disdice ancora (non è iniziata, la disdetta è aperta)
     */
    public function __construct(
        public LocalDate $date,
        public array $slots,
        public ?Booking $mine = null,
        public bool $mineCancellable = false,
    ) {}

    public function state(): DayState
    {
        $open = array_filter($this->slots, static fn(Slot $slot): bool => !$slot->closed);
        if ($open === []) {
            return DayState::Closed;
        }
        foreach ($open as $slot) {
            if (!$slot->isFull()) {
                return DayState::Open;
            }
        }

        return DayState::Full;
    }

    public function hasSlots(): bool
    {
        return $this->slots !== [];
    }
}
