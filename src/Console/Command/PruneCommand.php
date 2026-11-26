<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Account\Housekeeping;
use FourStreet\Console\Command;
use FourStreet\Console\Output;

/**
 * php bin/console prune: cancella sessioni, link delle email e contatori dei tentativi scaduti.
 * Lo fa già il sito a ogni accesso; il comando serve per un cron, se se ne vuole uno.
 */
final readonly class PruneCommand implements Command
{
    public const string NAME = 'prune';
    public const string DESCRIPTION = 'Cancella sessioni, link delle email e contatori dei tentativi scaduti.';

    public function __construct(
        private Housekeeping $housekeeping,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return self::DESCRIPTION;
    }

    public function run(array $arguments, Output $output): int
    {
        $deleted = $this->housekeeping->run();
        $output->line(sprintf(
            'Cancellati: %d sessioni, %d link delle email, %d contatori dei tentativi.',
            $deleted['sessions'],
            $deleted['tokens'],
            $deleted['rate_limits'],
        ));

        return 0;
    }
}
