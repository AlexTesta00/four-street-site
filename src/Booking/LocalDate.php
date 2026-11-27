<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Stringable;

/**
 * Un giorno del calendario, senza ora né fuso: "2026-12-07". Le fasce sono giorni locali del sito
 * (Europe/Rome): il giorno di un istante si chiede con of(), l'istante di una fascia con at().
 * I conti sui giorni si fanno sul calendario, mai sommando 24 ore: il cambio dell'ora non li sposta.
 */
final readonly class LocalDate implements Stringable
{
    private const string FORMAT = 'Y-m-d';

    private const string PATTERN = '/\A\d{4}-\d{2}-\d{2}\z/';

    private function __construct(
        public string $value,
    ) {}

    /** Il giorno scritto come AAAA-MM-GG, oppure null se il formato è diverso o il giorno non esiste (31 aprile). */
    public static function tryFrom(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $value, self::utc());

        return $date !== false && $date->format(self::FORMAT) === $value ? new self($value) : null;
    }

    /** Il giorno in cui cade un istante, nel fuso indicato (quello del sito). */
    public static function of(DateTimeImmutable $moment, DateTimeZone $timezone): self
    {
        return new self($moment->setTimezone($timezone)->format(self::FORMAT));
    }

    /** Lo stesso giorno spostato di $days giorni sul calendario (anche all'indietro). */
    public function addDays(int $days): self
    {
        return new self($this->calendarDay()->modify(sprintf('%+d days', $days))->format(self::FORMAT));
    }

    public function weekday(): Weekday
    {
        return Weekday::from((int) $this->calendarDay()->format('N'));
    }

    /**
     * L'istante in cui comincia quell'ora in questo giorno, nel fuso indicato, oppure null se quel giorno
     * l'ora non esiste: le 02:30 del giorno in cui si passa all'ora legale. Nel giorno del ritorno all'ora
     * solare un'ora doppia vale una volta sola, quella che sceglie PHP (la seconda).
     */
    public function at(SlotTime $time, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $text = $this->value . ' ' . $time->value;
        $moment = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $text, $timezone);

        return $moment !== false && $moment->format('Y-m-d H:i') === $text ? $moment : null;
    }

    /**
     * Il giorno come data a mezzanotte UTC: solo per leggerne giorno, mese, anno e giorno della settimana
     * (per esempio con ItalianDateFormatter), mai come istante.
     */
    public function calendarDay(): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $this->value, self::utc());
        if ($date === false) {
            // tryFrom() e of() scrivono solo giorni validi: qui non si arriva.
            throw new LogicException(sprintf('Giorno non valido: "%s".', $this->value));
        }

        return $date;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isBefore(self $other): bool
    {
        // AAAA-MM-GG si ordina come testo.
        return strcmp($this->value, $other->value) < 0;
    }

    public function isAfter(self $other): bool
    {
        return strcmp($this->value, $other->value) > 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
