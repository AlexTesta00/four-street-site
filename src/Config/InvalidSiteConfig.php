<?php

declare(strict_types=1);

namespace FourStreet\Config;

use RuntimeException;

/**
 * Un file di configurazione del sito (config/site.php o config/local.php) non è valido:
 * il sito non parte finché non viene corretto.
 */
final class InvalidSiteConfig extends RuntimeException {}
