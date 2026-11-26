<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Console;

use FourStreet\Account\Housekeeping;
use FourStreet\Account\Session\PdoSessionRepository;
use FourStreet\Account\Token\PdoAccountTokenRepository;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\Environment;
use FourStreet\Config\LocalConfig;
use FourStreet\Config\Paths;
use FourStreet\Config\SiteConfig;
use FourStreet\Console\Command\MigrateCommand;
use FourStreet\Console\Command\MigrationStatusCommand;
use FourStreet\Console\Command\NotConfiguredCommand;
use FourStreet\Console\Command\PruneCommand;
use FourStreet\Console\ConsoleApplication;
use FourStreet\Console\Output;
use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleApplication::class)]
#[CoversClass(Output::class)]
#[CoversClass(MigrateCommand::class)]
#[CoversClass(MigrationStatusCommand::class)]
#[CoversClass(PruneCommand::class)]
#[CoversClass(NotConfiguredCommand::class)]
#[UsesClass(Housekeeping::class)]
#[UsesClass(PdoSessionRepository::class)]
#[UsesClass(PdoAccountTokenRepository::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Row::class)]
#[UsesClass(UtcDateTime::class)]
#[UsesClass(PdoRateLimitStore::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(Environment::class)]
#[UsesClass(LocalConfig::class)]
#[UsesClass(Paths::class)]
#[UsesClass(SiteConfig::class)]
final class ConsoleApplicationTest extends TestCase
{
    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    protected function setUp(): void
    {
        $this->stdout = self::memory();
        $this->stderr = self::memory();
    }

    public function testWithoutACommandItListsThem(): void
    {
        $exit = $this->console()->run([], $this->streams());

        self::assertSame(0, $exit);
        self::assertSame(
            "Uso: php bin/console <comando>\n\nComandi:\n"
            . "  migrate         Applica al database le migrazioni che mancano (rilanciarlo non rifà nulla).\n"
            . "  migrate:status  Elenca le migrazioni e dice quali mancano.\n"
            . "  prune           Cancella sessioni, link delle email e contatori dei tentativi scaduti.\n",
            $this->written($this->stdout),
        );
    }

    public function testMigrateThenStatusThenMigrateAgain(): void
    {
        $console = $this->console();

        self::assertSame(0, $console->run(['migrate:status'], $this->streams()));
        self::assertStringEndsWith("4 migrazioni da applicare: php bin/console migrate\n", $this->written($this->stdout));

        self::assertSame(0, $console->run(['migrate'], $this->streams()));
        self::assertStringEndsWith(
            "Applicata 0001_create_users\nApplicata 0002_create_sessions\nApplicata 0003_create_account_tokens\nApplicata 0004_create_rate_limits\nDatabase aggiornato: 4 migrazioni applicate.\n",
            $this->written($this->stdout),
        );

        self::assertSame(0, $console->run(['migrate'], $this->streams()));
        self::assertStringEndsWith("Il database è già aggiornato: nessuna migrazione da applicare.\n", $this->written($this->stdout));

        self::assertSame(0, $console->run(['migrate:status'], $this->streams()));
        self::assertStringEndsWith("[x] 0004_create_rate_limits\nIl database è aggiornato.\n", $this->written($this->stdout));

        self::assertSame(0, $console->run(['prune'], $this->streams()));
        self::assertStringEndsWith("Cancellati: 0 sessioni, 0 link delle email, 0 contatori dei tentativi.\n", $this->written($this->stdout));
        self::assertSame('', $this->written($this->stderr));
    }

    public function testAnUnknownCommand(): void
    {
        self::assertSame(1, $this->console()->run(['migra'], $this->streams()));
        self::assertSame("Comando sconosciuto: \"migra\".\n", $this->written($this->stderr));
        self::assertStringStartsWith('Uso: php bin/console <comando>', $this->written($this->stdout));
    }

    public function testAFailingCommandExitsWithAnError(): void
    {
        $temporary = new TemporaryDirectory();
        try {
            $console = ConsoleApplication::create($temporary->path, FrozenClock::at('2026-11-25 08:00:00'), new Database(DatabaseConfig::sqlite(':memory:')));

            self::assertSame(1, $console->run(['migrate'], $this->streams()));
            self::assertStringStartsWith('migrate non riuscito: Cartella delle migrazioni non trovata', $this->written($this->stderr));
        } finally {
            $temporary->remove();
        }
    }

    public function testWithoutADatabaseTheCommandsExplainWhatToDo(): void
    {
        $console = ConsoleApplication::create(SiteFixture::rootDirectory(), FrozenClock::at('2026-11-25 08:00:00'), null);

        self::assertSame(1, $console->run(['migrate'], $this->streams()));
        self::assertSame("Il database non è configurato: crea config/local.php partendo da config/local.example.php.\n", $this->written($this->stderr));
        self::assertSame(0, $console->run(['list'], $this->streams()));
        self::assertStringContainsString('  migrate:status  Elenca le migrazioni', $this->written($this->stdout));
    }

    public function testThePackageWithoutLocalConfigurationIsNotConfigured(): void
    {
        $temporary = new TemporaryDirectory();
        try {
            $package = $temporary->directory('_app');
            $temporary->file('_app/config/site.php', (string) file_get_contents(SiteFixture::rootDirectory() . '/config/site.php'));

            self::assertSame(1, ConsoleApplication::fromAppRoot($package)->run(['migrate'], $this->streams()));
            self::assertStringStartsWith('Il database non è configurato', $this->written($this->stderr));
        } finally {
            $temporary->remove();
        }
    }

    public function testALocalConfigurationChoosesTheDatabase(): void
    {
        $temporary = new TemporaryDirectory();
        try {
            $file = $temporary->file('local.php', '<?php return ' . var_export([
                'secret' => str_repeat('s', 32),
                'database' => ['dsn' => 'sqlite:' . $temporary->path . '/4street.sqlite'],
                'mail' => ['transport' => 'file', 'directory' => $temporary->path . '/mail', 'from' => 'prove@fourstreet.studio'],
            ], true) . ';');

            self::assertSame(0, ConsoleApplication::fromAppRoot(SiteFixture::rootDirectory(), $file)->run(['migrate'], $this->streams()));
            self::assertFileExists($temporary->path . '/4street.sqlite');
        } finally {
            gc_collect_cycles();
            $temporary->remove();
        }
    }

    private function console(): ConsoleApplication
    {
        return ConsoleApplication::create(SiteFixture::rootDirectory(), FrozenClock::at('2026-11-25 08:00:00'), new Database(DatabaseConfig::sqlite(':memory:')));
    }

    private function streams(): Output
    {
        return new Output($this->stdout, $this->stderr);
    }

    /**
     * @param resource $stream
     */
    private function written(mixed $stream): string
    {
        rewind($stream);

        return str_replace(PHP_EOL, "\n", (string) stream_get_contents($stream));
    }

    /**
     * @return resource
     */
    private static function memory(): mixed
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        return $stream;
    }
}
