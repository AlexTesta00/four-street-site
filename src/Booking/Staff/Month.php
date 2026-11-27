<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use LogicException;
use Stringable;

/**
 * Un mese del calendario, "2026-12": quello che il pannello del titolare mostra a colpo d'occhio.
 */
final readonly class Month implements Stringable
{
    private const string PATTERN = '/\A\d{4}-(?:0[1-9]|1[0-2])\z/';

    private function __construct(
        public string $value,
    ) {}

    /** Il mese scritto come AAAA-MM, oppure null se il formato è diverso. */
    public static function tryFrom(string $value): ?self
    {
        return preg_match(self::PATTERN, $value) === 1 && LocalDate::tryFrom($value . '-01') !== null ? new self($value) : null;
    }

    /** Il mese di quel giorno. */
    public static function of(LocalDate $day): self
    {
        return new self(substr($day->value, 0, 7));
    }

    public function first(): LocalDate
    {
        return LocalDate::tryFrom($this->value . '-01') ?? throw new LogicException(sprintf('Mese non valido: "%s".', $this->value));
    }

    public function last(): LocalDate
    {
        $first = $this->first();

        return LocalDate::tryFrom($first->calendarDay()->format('Y-m-t')) ?? throw new LogicException(sprintf('Mese non valido: "%s".', $this->value));
    }

    public function previous(): self
    {
        return self::of($this->first()->addDays(-1));
    }

    public function next(): self
    {
        return self::of($this->last()->addDays(1));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
