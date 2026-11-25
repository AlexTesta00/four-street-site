<?php

declare(strict_types=1);

namespace FourStreet\Schedule;

/**
 * Una casella del countdown. La chiave finisce in data-unit: lo script la usa per aggiornare le cifre.
 */
final readonly class CountdownUnit
{
    /** Con due cifre si arriva a 99: oltre, il countdown resta fermo su 99 giorni. */
    private const int MAX_SHOWN = 99;

    public function __construct(
        public string $key,
        public string $label,
        public int $value,
    ) {}

    /**
     * Cifre da mostrare, sempre due.
     *
     * @return list<string>
     */
    public function digits(): array
    {
        return str_split(sprintf('%02d', min(self::MAX_SHOWN, $this->value)));
    }
}
