<?php

declare(strict_types=1);

namespace FourStreet\Asset;

/**
 * Data di ultima modifica di un file (timestamp Unix), oppure null se il file non c'è.
 */
interface MtimeLookup
{
    public function mtime(string $file): ?int;
}
