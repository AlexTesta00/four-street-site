<?php

declare(strict_types=1);

namespace FourStreet\Admin\Form;

use FourStreet\Booking\SlotTime;

/**
 * L'orario d'inizio di una fascia scritto dal titolare. Il campo è un <input type="time">: sul telefono apre il
 * selettore dell'ora e manda "18:30" (alcuni browser anche i secondi, "18:30:00"). Chi lo scrive a mano, in un browser
 * senza selettore, mette anche "7:30", "18.30" o "7.30": vanno bene tutti, e diventano un SlotTime ("07:30").
 * Il resto è un errore che dice come scriverlo.
 */
final class TimeField
{
    public const string MISSING = 'Scrivi l\'orario della fascia, come 18:30.';

    public const string WRONG = 'Scrivi l\'orario a 24 ore, come 18:30.';

    /** Ore e minuti, con i due punti o con il punto; i secondi solo a zero, come li manda il selettore. */
    private const string PATTERN = '/\A(\d{1,2})[:.](\d{2})(?::00(?:\.0{1,3})?)?\z/';

    /**
     * L'orario scritto, oppure il messaggio da mostrare sul campo.
     */
    public static function parse(string $input): SlotTime|string
    {
        $text = trim($input);
        if ($text === '') {
            return self::MISSING;
        }
        if (preg_match(self::PATTERN, $text, $parts) !== 1) {
            return self::WRONG;
        }

        // "7" diventa "07"; ore e minuti fuori misura ("24:00", "18:75") li scarta SlotTime.
        return SlotTime::tryFrom(sprintf('%02d:%s', (int) $parts[1], $parts[2])) ?? self::WRONG;
    }

    /** Il valore da rimettere nel campo dopo un errore: l'orario come lo vuole il campo ("07:30") se si legge, altrimenti com'è arrivato. */
    public static function refill(string $input): string
    {
        $time = self::parse($input);

        return $time instanceof SlotTime ? $time->value : $input;
    }
}
