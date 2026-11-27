<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Weekday;
use FourStreet\Text\ItalianDateFormatter;

/**
 * I giorni come li scrivono le pagine dell'orario, con l'anno solo quando non è quello di oggi: un giorno ("giovedì
 * 24 dicembre"), un intervallo ("da giovedì 24 a sabato 26 dicembre"), una settimana ("dal 7 al 13 dicembre",
 * "dal 28 dicembre 2026 al 3 gennaio 2027") e i giorni della settimana tipo ("dal lunedì", "nelle domeniche").
 */
final readonly class ScheduleDates
{
    /** I mesi a parole, come in ItalianDateFormatter (che li tiene per sé). */
    private const array MONTHS = [
        1 => 'gennaio',
        'febbraio',
        'marzo',
        'aprile',
        'maggio',
        'giugno',
        'luglio',
        'agosto',
        'settembre',
        'ottobre',
        'novembre',
        'dicembre',
    ];

    public function __construct(
        private ItalianDateFormatter $dates,
        public LocalDate $today,
    ) {}

    /** "giovedì 24 dicembre", con l'anno se non è quello di oggi. */
    public function day(LocalDate $date, bool $capitalize = false): string
    {
        return $this->dates->format($date->calendarDay(), withYear: !$this->isThisYear($date), capitalize: $capitalize);
    }

    /**
     * Uno o più giorni di fila, con i giorni della settimana: "giovedì 24 dicembre", "da giovedì 24 a sabato
     * 26 dicembre", "da lunedì 30 novembre a mercoledì 2 dicembre", "da giovedì 31 dicembre 2026 a sabato 2 gennaio 2027".
     */
    public function range(LocalDate $from, LocalDate $to, bool $capitalize = false): string
    {
        if ($from->equals($to)) {
            return $this->day($from, $capitalize);
        }

        $first = $from->calendarDay();
        $last = $to->calendarDay();
        if ($first->format('Y') !== $last->format('Y')) {
            $text = 'da ' . $this->dates->format($first, capitalize: false) . ' a ' . $this->dates->format($last, capitalize: false);
        } else {
            $start = $from->weekday()->label() . ' ' . $first->format('j') . ($first->format('n') === $last->format('n') ? '' : ' ' . self::month($from));
            $text = 'da ' . $start . ' a ' . $this->day($to);
        }

        return $capitalize ? ucfirst($text) : $text;
    }

    /**
     * Uno o più giorni di fila, senza i giorni della settimana (il titolo di una settimana): "dal 7 al 13 dicembre",
     * "dall'8 al 14 febbraio", "dal 30 novembre al 6 dicembre", "dal 28 dicembre 2026 al 3 gennaio 2027".
     */
    public function span(LocalDate $from, LocalDate $to, bool $capitalize = false): string
    {
        $first = $from->calendarDay();
        $last = $to->calendarDay();
        $start = self::elided((int) $first->format('j')) ? 'dall\'' . $first->format('j') : 'dal ' . $first->format('j');
        $end = self::elided((int) $last->format('j')) ? 'all\'' . $last->format('j') : 'al ' . $last->format('j');

        if ($first->format('Y') !== $last->format('Y')) {
            $text = sprintf('%s %s %s %s %s %s', $start, self::month($from), $first->format('Y'), $end, self::month($to), $last->format('Y'));
        } else {
            $year = $this->isThisYear($to) ? '' : ' ' . $last->format('Y');
            $text = $first->format('n') === $last->format('n')
                ? sprintf('%s %s %s%s', $start, $end, self::month($to), $year)
                : sprintf('%s %s %s %s%s', $start, self::month($from), $end, self::month($to), $year);
        }

        return $capitalize ? ucfirst($text) : $text;
    }

    /** "dal lunedì", "dalla domenica": da quale giorno della settimana tipo si toglie un orario. */
    public static function fromWeekday(Weekday $day): string
    {
        return ($day === Weekday::Sunday ? 'dalla ' : 'dal ') . $day->label();
    }

    /** "al lunedì", "alla domenica": a quale giorno della settimana tipo si aggiunge un orario. */
    public static function toWeekday(Weekday $day): string
    {
        return ($day === Weekday::Sunday ? 'alla ' : 'al ') . $day->label();
    }

    /** "nei lunedì", "nei sabati", "nelle domeniche": i giorni che seguono la settimana tipo. */
    public static function everyWeekday(Weekday $day): string
    {
        return match ($day) {
            Weekday::Saturday => 'nei sabati',
            Weekday::Sunday => 'nelle domeniche',
            default => 'nei ' . $day->label(),
        };
    }

    private function isThisYear(LocalDate $date): bool
    {
        return $date->calendarDay()->format('Y') === $this->today->calendarDay()->format('Y');
    }

    private static function month(LocalDate $date): string
    {
        return self::MONTHS[(int) $date->calendarDay()->format('n')];
    }

    /** "dall'8", "all'11": l'articolo si elide davanti a un numero che si legge con la vocale. */
    private static function elided(int $day): bool
    {
        return $day === 8 || $day === 11;
    }
}
