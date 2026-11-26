<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Console\Command;
use FourStreet\Console\Output;
use FourStreet\Database\Migration\Migrator;

/**
 * php bin/console migrate:status: le migrazioni, una per riga, applicate o da applicare.
 */
final readonly class MigrationStatusCommand implements Command
{
    public const string NAME = 'migrate:status';
    public const string DESCRIPTION = 'Elenca le migrazioni e dice quali mancano.';

    public function __construct(
        private Migrator $migrator,
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
        $pending = 0;
        foreach ($this->migrator->status() as $version => $applied) {
            $output->line(($applied ? '[x] ' : '[ ] ') . $version);
            $pending += $applied ? 0 : 1;
        }

        $output->line(match ($pending) {
            0 => 'Il database è aggiornato.',
            1 => '1 migrazione da applicare: php bin/console migrate',
            default => sprintf('%d migrazioni da applicare: php bin/console migrate', $pending),
        });

        return 0;
    }
}
