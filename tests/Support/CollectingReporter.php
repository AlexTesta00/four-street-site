<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Error\ExceptionReporter;
use Throwable;

final class CollectingReporter implements ExceptionReporter
{
    /** @var list<Throwable> */
    public array $reported = [];

    public function report(Throwable $error): void
    {
        $this->reported[] = $error;
    }
}
