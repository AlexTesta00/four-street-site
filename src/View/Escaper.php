<?php

declare(strict_types=1);

namespace FourStreet\View;

/**
 * Escape per testo e attributi HTML.
 */
final class Escaper
{
    /**
     * Testo pronto per il markup: virgolette comprese, UTF-8 non valido sostituito.
     * Il markup già sicuro (Html) passa invariato, così non viene mai escapato due volte.
     */
    public static function html(Html|string|int|null $value): string
    {
        if ($value instanceof Html) {
            return (string) $value;
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
