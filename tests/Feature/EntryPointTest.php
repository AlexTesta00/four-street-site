<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature;

use FourStreet\Application;
use FourStreet\Config\LocalConfig;
use FourStreet\Console\ConsoleApplication;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * public/index.php e bin/console eseguiti davvero, in un processo PHP a parte, con la configurazione vera.
 * Le risposte dipendono dall'ora reale, quindi si controlla solo ciò che non cambia nel tempo.
 */
#[CoversClass(Application::class)]
#[CoversClass(ConsoleApplication::class)]
final class EntryPointTest extends TestCase
{
    public function testServesTheCalendar(): void
    {
        $output = self::serve('GET', '/calendario.php');

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $output);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $output);
    }

    public function testServesThePages(): void
    {
        self::assertStringContainsString('<title>4Street · Link</title>', self::serve('GET', '/links/'));
        self::assertStringContainsString('<title>4Street · Pagina non trovata</title>', self::serve('GET', '/non-esiste'));
    }

    public function testHeadHasNoBody(): void
    {
        self::assertSame('', self::serve('HEAD', '/links/'));
    }

    public function testTheEnvironmentVariableChoosesTheLocalConfigurationOfSiteAndConsole(): void
    {
        $temporary = new TemporaryDirectory();
        try {
            $config = self::localConfig($temporary);

            $login = self::serve('GET', '/account/accedi/', [LocalConfig::FILE_VARIABLE => $config]);
            self::assertStringContainsString('<title>4Street · Accedi</title>', $login);

            [$exitCode, $output] = self::runPhp([SiteFixture::rootDirectory() . '/bin/console', 'migrate'], [LocalConfig::FILE_VARIABLE => $config]);
            self::assertSame(0, $exitCode);
            self::assertStringContainsString('Database aggiornato: 5 migrazioni applicate.', $output);
            self::assertFileExists($temporary->path . '/4street.sqlite');
        } finally {
            $temporary->remove();
        }
    }

    public function testTheHomeReadsTheSlotsFromTheConfiguredDatabase(): void
    {
        // Con le prenotazioni attive la home legge i posti: dal database indicato, già migrato, senza errori nel log.
        $temporary = new TemporaryDirectory();
        try {
            $config = self::localConfig($temporary);
            [$exitCode] = self::runPhp([SiteFixture::rootDirectory() . '/bin/console', 'migrate'], [LocalConfig::FILE_VARIABLE => $config]);
            self::assertSame(0, $exitCode);

            $home = self::serve('GET', '/?anteprima=mappa', [LocalConfig::FILE_VARIABLE => $config]);

            self::assertStringContainsString('<span data-state="open">Siamo aperti.</span>', $home);
            self::assertStringContainsString('id="fasce"', $home);
        } finally {
            $temporary->remove();
        }
    }

    public function testAnErrorAtStartupShowsNothingAndGoesToTheLog(): void
    {
        // Una configurazione locale che non c'è ferma l'avvio: nella risposta nessun percorso del server,
        // qualunque cosa dica il php.ini; l'errore è nel log.
        $temporary = new TemporaryDirectory();
        try {
            $log = $temporary->path . '/php.log';
            [$exitCode, $output] = self::runPhp(
                ['-d', 'display_errors=1', '-d', 'error_log=' . $log, SiteFixture::rootDirectory() . '/public/index.php'],
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', LocalConfig::FILE_VARIABLE => $temporary->path . '/manca.php'],
            );

            self::assertSame(255, $exitCode);
            self::assertSame('', $output);
            self::assertFileExists($log);
            self::assertStringContainsString('Configurazione locale non trovata', (string) file_get_contents($log));
        } finally {
            $temporary->remove();
        }
    }

    /** Una configurazione locale nella cartella temporanea: SQLite e posta in file. */
    private static function localConfig(TemporaryDirectory $temporary): string
    {
        return $temporary->file('local.php', '<?php return ' . var_export([
            'base_url' => 'http://127.0.0.1:8089',
            'secret' => str_repeat('s', 32),
            'database' => ['dsn' => 'sqlite:' . $temporary->path . '/4street.sqlite'],
            'mail' => ['transport' => 'file', 'directory' => $temporary->path . '/mail', 'from' => 'prove@fourstreet.studio'],
        ], true) . ';');
    }

    public function testTheConsoleListsItsCommands(): void
    {
        [$exitCode, $output] = self::runPhp([SiteFixture::rootDirectory() . '/bin/console']);

        self::assertSame(0, $exitCode);
        self::assertStringStartsWith('Uso: php bin/console <comando>', $output);
    }

    /**
     * @param array<string, string> $environment
     */
    private static function serve(string $method, string $uri, array $environment = []): string
    {
        [$exitCode, $output] = self::runPhp(
            [SiteFixture::rootDirectory() . '/public/index.php'],
            ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, ...$environment],
        );
        self::assertSame(0, $exitCode);

        return $output;
    }

    /**
     * Esegue uno script PHP: l'uscita degli errori deve restare vuota.
     *
     * @param list<string> $arguments
     * @param array<string, string> $environment
     *
     * @return array{int, string} codice d'uscita e uscita standard
     */
    private static function runPhp(array $arguments, array $environment = []): array
    {
        $process = proc_open(
            [PHP_BINARY, ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), ...$environment],
        );
        self::assertIsResource($process);
        self::assertArrayHasKey(1, $pipes);
        self::assertArrayHasKey(2, $pipes);

        [1 => $stdout, 2 => $stderr] = $pipes;
        $output = (string) stream_get_contents($stdout);
        $errors = (string) stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        $exitCode = proc_close($process);

        self::assertSame('', $errors);

        return [$exitCode, $output];
    }
}
