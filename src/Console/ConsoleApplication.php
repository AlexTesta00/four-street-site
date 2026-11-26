<?php

declare(strict_types=1);

namespace FourStreet\Console;

use FourStreet\Account\Housekeeping;
use FourStreet\Account\Session\PdoSessionRepository;
use FourStreet\Account\Token\PdoAccountTokenRepository;
use FourStreet\Clock\Clock;
use FourStreet\Clock\SystemClock;
use FourStreet\Config\LocalConfig;
use FourStreet\Config\SiteConfig;
use FourStreet\Console\Command\MigrateCommand;
use FourStreet\Console\Command\MigrationStatusCommand;
use FourStreet\Console\Command\NotConfiguredCommand;
use FourStreet\Console\Command\PruneCommand;
use FourStreet\Database\Database;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use Throwable;

/**
 * La console dell'applicazione (bin/console): sceglie il comando dal primo argomento e lo esegue.
 * Legge la stessa configurazione del sito, quindi in sviluppo usa il database SQLite di var/
 * e sul server quello di config/local.php.
 */
final readonly class ConsoleApplication
{
    /**
     * @param list<Command> $commands
     */
    public function __construct(
        private array $commands,
    ) {}

    /**
     * @param string $appRoot la radice dell'applicazione (repo in sviluppo, _app/ nel pacchetto)
     * @param string|null $localConfigFile la configurazione locale indicata dalla variabile d'ambiente, se c'è
     */
    public static function fromAppRoot(string $appRoot, ?string $localConfigFile = null): self
    {
        $site = SiteConfig::fromFile($appRoot . '/config/site.php');
        $local = LocalConfig::load($localConfigFile, $appRoot, $site->siteUrl);

        return self::create($appRoot, new SystemClock($site->timezone), $local === null ? null : new Database($local->database));
    }

    /**
     * Tutti i comandi. Quelli che usano il database, senza database, spiegano come configurarlo.
     */
    public static function create(string $appRoot, Clock $clock, ?Database $database): self
    {
        if ($database === null) {
            return new self([
                new NotConfiguredCommand(MigrateCommand::NAME, MigrateCommand::DESCRIPTION),
                new NotConfiguredCommand(MigrationStatusCommand::NAME, MigrationStatusCommand::DESCRIPTION),
                new NotConfiguredCommand(PruneCommand::NAME, PruneCommand::DESCRIPTION),
            ]);
        }

        $migrator = new Migrator($database, $appRoot . '/database/migrations', $clock);
        $housekeeping = new Housekeeping(
            new PdoSessionRepository($database),
            new PdoAccountTokenRepository($database),
            new PdoRateLimitStore($database),
            $clock,
        );

        return new self([
            new MigrateCommand($migrator),
            new MigrationStatusCommand($migrator),
            new PruneCommand($housekeeping),
        ]);
    }

    /**
     * @param list<string> $arguments gli argomenti dopo "bin/console": il comando e i suoi
     *
     * @return int codice d'uscita
     */
    public function run(array $arguments, Output $output): int
    {
        $name = $arguments[0] ?? null;
        if ($name === null || in_array($name, ['list', 'help', '--help', '-h'], true)) {
            $this->help($output);

            return 0;
        }

        foreach ($this->commands as $command) {
            if ($command->name() !== $name) {
                continue;
            }

            try {
                return $command->run(array_slice($arguments, 1), $output);
            } catch (Throwable $error) {
                $output->error(sprintf('%s non riuscito: %s', $name, $error->getMessage()));

                return 1;
            }
        }

        $output->error(sprintf('Comando sconosciuto: "%s".', $name));
        $this->help($output);

        return 1;
    }

    private function help(Output $output): void
    {
        $output->line('Uso: php bin/console <comando>');
        $output->line();
        $output->line('Comandi:');
        $width = max([0, ...array_map(static fn(Command $command): int => strlen($command->name()), $this->commands)]);
        foreach ($this->commands as $command) {
            $output->line(sprintf('  %s  %s', str_pad($command->name(), $width), $command->description()));
        }
    }
}
