<?php

declare(strict_types=1);

namespace FourStreet\Config;

/**
 * Percorsi scritti nella configurazione: un percorso relativo parte dalla radice dell'applicazione,
 * mai dalla cartella da cui il server o la console sono stati avviati.
 */
final class Paths
{
    public static function resolve(string $path, string $appRoot): string
    {
        return self::isAbsolute($path) ? $path : rtrim($appRoot, '/\\') . '/' . $path;
    }

    /** "/srv/…", "\\server\…" oppure "C:\…" e "C:/…" su Windows. */
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('#\A[A-Za-z]:[/\\\\]#', $path) === 1;
    }
}
