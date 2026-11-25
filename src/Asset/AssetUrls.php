<?php

declare(strict_types=1);

namespace FourStreet\Asset;

use InvalidArgumentException;

/**
 * URL dei file statici con la versione (?v=<mtime>): dopo un deploy il browser non tiene copie vecchie.
 */
final readonly class AssetUrls
{
    /**
     * @param string $publicDirectory cartella pubblica del sito (dove sta index.php)
     */
    public function __construct(
        private string $publicDirectory,
        private MtimeLookup $mtimes,
    ) {}

    /**
     * @param string $path percorso dalla radice del sito, per esempio "/assets/css/app.css"
     */
    public function url(string $path): string
    {
        if (!str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            throw new InvalidArgumentException(sprintf('Percorso di asset non valido: "%s".', $path));
        }
        $mtime = $this->mtimes->mtime(rtrim($this->publicDirectory, '/\\') . $path);

        return $mtime === null ? $path : $path . '?v=' . $mtime;
    }
}
