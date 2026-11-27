<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

/**
 * Perché una modifica dell'orario non si fa, a parole, e su cosa: il giorno ("è un giorno passato") oppure
 * l'orario ("le 18:30 ci sono già"). La pagina mette il messaggio accanto al campo giusto.
 */
final readonly class ScheduleRefusal
{
    private function __construct(
        public string $message,
        public bool $aboutTime,
    ) {}

    /** Un problema del giorno (o dell'intervallo di giorni). */
    public static function day(string $message): self
    {
        return new self($message, false);
    }

    /** Un problema dell'orario aggiunto o tolto. */
    public static function time(string $message): self
    {
        return new self($message, true);
    }
}
