<?php

declare(strict_types=1);

namespace FourStreet\Schedule;

/**
 * Anteprime per provare gli stati prima dell'apertura: ?anteprima=mappa oppure ?anteprima=zero.
 */
enum Preview: string
{
    /** La pagina come se l'inaugurazione fosse già iniziata. */
    case Map = 'mappa';

    /** Il countdown scade tra pochi secondi, per vedere il passaggio allo stato "aperti". */
    case Zero = 'zero';

    public static function fromQuery(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
