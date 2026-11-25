<?php

declare(strict_types=1);

namespace FourStreet\Error;

use Throwable;

/**
 * Registra un errore imprevisto: il visitatore vede una pagina generica, i dettagli finiscono qui.
 */
interface ExceptionReporter
{
    public function report(Throwable $error): void;
}
