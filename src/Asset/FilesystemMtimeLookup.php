<?php

declare(strict_types=1);

namespace FourStreet\Asset;

/**
 * Legge la data di modifica dal disco.
 */
final class FilesystemMtimeLookup implements MtimeLookup
{
    public function mtime(string $file): ?int
    {
        if (!is_file($file)) {
            return null;
        }
        $mtime = filemtime($file);

        return $mtime === false ? null : $mtime;
    }
}
