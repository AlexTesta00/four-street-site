<?php

declare(strict_types=1);

namespace FourStreet\Text;

use DateTimeInterface;

/**
 * Date in italiano senza dipendere da intl o dal locale del server: "Domenica 6 dicembre 2026".
 * Usa il fuso della data ricevuta, non quello predefinito di PHP.
 */
final class ItalianDateFormatter
{
    private const array WEEKDAYS = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];

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

    public function format(DateTimeInterface $date, bool $withYear = true, bool $capitalize = true): string
    {
        $text = sprintf(
            '%s %d %s',
            self::WEEKDAYS[(int) $date->format('w')],
            (int) $date->format('j'),
            self::MONTHS[(int) $date->format('n')],
        );
        if ($withYear) {
            $text .= ' ' . $date->format('Y');
        }

        // I giorni iniziano tutti con una lettera ASCII: ucfirst basta.
        return $capitalize ? ucfirst($text) : $text;
    }
}
