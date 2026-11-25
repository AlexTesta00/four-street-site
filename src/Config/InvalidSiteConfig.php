<?php

declare(strict_types=1);

namespace FourStreet\Config;

use RuntimeException;

/**
 * La configurazione del sito non è valida: il sito non parte finché non viene corretta.
 */
final class InvalidSiteConfig extends RuntimeException {}
