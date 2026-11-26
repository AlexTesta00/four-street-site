<?php

declare(strict_types=1);

namespace FourStreet\Config;

/**
 * Dove gira l'applicazione. Lo dice la sua cartella, la stessa regola di public/index.php:
 * nel pacchetto di deploy l'applicazione sta in _app/, in sviluppo è la radice della repo.
 */
enum Environment
{
    /** Dalla radice della repo: senza config/local.php valgono i valori di sviluppo (SQLite, posta in file). */
    case Development;

    /** Dal pacchetto di deploy (_app/): senza config/local.php l'area clienti non è configurata. */
    case Production;

    public const string PACKAGE_DIRECTORY = '_app';

    public static function of(string $appRoot): self
    {
        return basename(rtrim($appRoot, '/\\')) === self::PACKAGE_DIRECTORY ? self::Production : self::Development;
    }
}
