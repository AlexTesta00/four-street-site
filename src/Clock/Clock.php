<?php

declare(strict_types=1);

namespace FourStreet\Clock;

use DateTimeImmutable;

/**
 * Fonte dell'ora corrente. In produzione l'orologio di sistema, nei test un orologio fermo.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
