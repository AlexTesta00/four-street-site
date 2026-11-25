<?php

declare(strict_types=1);

namespace FourStreet\Error;

use Throwable;

/**
 * Scrive l'errore nel log di PHP del server.
 */
final class ErrorLogReporter implements ExceptionReporter
{
    public function report(Throwable $error): void
    {
        error_log('[4Street] ' . $error);
    }
}
