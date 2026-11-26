<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Console\Command;
use FourStreet\Console\Output;
use FourStreet\Database\Migration\Migrator;

/**
 * php bin/console migrate: applica le migrazioni che mancano. Rilanciarlo non rifà nulla.
 */
final readonly class MigrateCommand implements Command
{
    public const string NAME = 'migrate';
    public const string DESCRIPTION = 'Applica al database le migrazioni che mancano (rilanciarlo non rifà nulla).';

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
        $applied = $this->migrator->migrate(static function (string $version) use ($output): void {
            $output->line('Applicata ' . $version);
        });

        $count = count($applied);
        $output->line(match ($count) {
            0 => 'Il database è già aggiornato: nessuna migrazione da applicare.',
            1 => 'Database aggiornato: 1 migrazione applicata.',
            default => sprintf('Database aggiornato: %d migrazioni applicate.', $count),
        });

        return 0;
    }
}
