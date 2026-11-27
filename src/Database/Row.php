<?php

declare(strict_types=1);

namespace FourStreet\Database;

use DateTimeImmutable;
use UnexpectedValueException;

/**
 * Una riga letta dal database, con letture tipizzate: un valore del tipo sbagliato è un errore, mai una sorpresa.
 */
final readonly class Row
{
    /**
     * @param array<string, mixed> $values colonna => valore, come le restituisce PDO
     */
    public function __construct(
        private array $values,
    ) {}

    public function int(string $column): int
    {
        $value = $this->value($column);
        if (is_int($value)) {
            return $value;
        }
        // Alcuni driver restituiscono i numeri come testo: si accettano solo se sono interi esatti.
        if (is_string($value) && preg_match('/\A-?\d{1,18}\z/', $value) === 1) {
            return (int) $value;
        }

        throw self::unexpected($column, 'un numero intero', $value);
    }

    public function nullableInt(string $column): ?int
    {
        return $this->value($column) === null ? null : $this->int($column);
    }

    public function string(string $column): string
    {
        $value = $this->value($column);
        if (!is_string($value)) {
            throw self::unexpected($column, 'un testo', $value);
        }

        return $value;
    }

    public function nullableString(string $column): ?string
    {
        return $this->value($column) === null ? null : $this->string($column);
    }

    public function dateTime(string $column): DateTimeImmutable
    {
        return UtcDateTime::parse($this->string($column));
    }

    public function nullableDateTime(string $column): ?DateTimeImmutable
    {
        return $this->value($column) === null ? null : $this->dateTime($column);
    }

    private function value(string $column): mixed
    {
        if (!array_key_exists($column, $this->values)) {
            throw new UnexpectedValueException(sprintf('La riga non ha la colonna "%s".', $column));
        }

        return $this->values[$column];
    }

    private static function unexpected(string $column, string $expected, mixed $found): UnexpectedValueException
    {
        return new UnexpectedValueException(sprintf('La colonna "%s" deve contenere %s (trovato: %s).', $column, $expected, get_debug_type($found)));
    }
}
