<?php

declare(strict_types=1);

namespace FourStreet\Calendar;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Un evento del calendario (VEVENT).
 */
final readonly class IcsEvent
{
    /**
     * @param string $uid identificativo stabile: lo stesso evento ha sempre lo stesso, così un secondo file lo aggiorna
     * @param bool $allDay evento di tutto il giorno (orario non confermato): conta solo la data locale di $start
     * @param DateTimeImmutable $stamp istante in cui l'evento è stato generato (DTSTAMP)
     * @param DateTimeImmutable|null $end la fine (DTEND), per gli eventi con l'orario; senza, l'evento è un istante
     * @param string|null $description il testo dell'evento (DESCRIPTION)
     * @param string|null $url la pagina dell'evento (URL), un indirizzo http(s) assoluto
     */
    public function __construct(
        public string $uid,
        public string $summary,
        public string $location,
        public DateTimeImmutable $start,
        public bool $allDay,
        public DateTimeImmutable $stamp,
        public ?DateTimeImmutable $end = null,
        public ?string $description = null,
        public ?string $url = null,
    ) {
        if ($end !== null && ($allDay || $end <= $start)) {
            throw new InvalidArgumentException('La fine di un evento con l\'orario viene dopo l\'inizio (e un evento di tutto il giorno non ne ha).');
        }
        if ($url !== null && preg_match('#\Ahttps?://[^\s\x00-\x1F\x7F"<>\\\\^`{|}]+\z#', $url) !== 1) {
            throw new InvalidArgumentException(sprintf('Indirizzo dell\'evento non valido: "%s".', $url));
        }
    }
}
