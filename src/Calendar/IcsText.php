<?php

declare(strict_types=1);

namespace FourStreet\Calendar;

/**
 * Regole di scrittura del formato iCalendar (RFC 5545) per testi e righe.
 */
final class IcsText
{
    /** Lunghezza massima di una riga, in byte, a capo escluso. */
    private const int LINE_OCTETS = 75;

    /** Un carattere UTF-8 ha al massimo tre byte di continuazione. */
    private const int MAX_CONTINUATION_BYTES = 3;

    /**
     * Escape di un valore di tipo TEXT: barra rovesciata, punto e virgola, virgola e a capo.
     */
    public static function escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }

    /**
     * Spezza una riga più lunga di 75 byte: le continuazioni iniziano con uno spazio.
     * Non taglia mai a metà un carattere UTF-8.
     *
     * @return non-empty-list<string>
     */
    public static function fold(string $line): array
    {
        $parts = [];
        while (strlen($line) > self::LINE_OCTETS) {
            $cut = self::LINE_OCTETS;
            // Un byte 10xxxxxx continua il carattere precedente: si taglia all'inizio del carattere.
            while ($cut > self::LINE_OCTETS - self::MAX_CONTINUATION_BYTES && (ord($line[$cut]) & 0xC0) === 0x80) {
                --$cut;
            }
            $parts[] = substr($line, 0, $cut);
            // Lo spazio iniziale della continuazione conta nei 75 byte della riga.
            $line = ' ' . substr($line, $cut);
        }
        $parts[] = $line;

        return $parts;
    }
}
