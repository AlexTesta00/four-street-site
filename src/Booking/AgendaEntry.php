<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;

/**
 * Una prenotazione nell'elenco del cliente: quando comincia e finisce (nel fuso del sito), se è in corso
 * e se si può ancora disdire.
 */
final readonly class AgendaEntry
{
    public function __construct(
        public Booking $booking,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public bool $inProgress,
        public bool $cancellable,
    ) {}
}
