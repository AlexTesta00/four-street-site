<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature;

use FourStreet\Application;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * public/index.php eseguito davvero, in un processo PHP a parte, con la configurazione vera.
 * Le risposte dipendono dall'ora reale, quindi si controlla solo ciò che non cambia nel tempo.
 */
#[CoversClass(Application::class)]
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
        self::assertStringContainsString('<span data-state="open">Siamo aperti.</span>', self::serve('GET', '/?anteprima=mappa'));
        self::assertStringContainsString('<title>4Street · Pagina non trovata</title>', self::serve('GET', '/non-esiste'));
    }

    public function testHeadHasNoBody(): void
    {
        self::assertSame('', self::serve('HEAD', '/links/'));
    }

    private static function serve(string $method, string $uri): string
    {
        $process = proc_open(
            [PHP_BINARY, SiteFixture::rootDirectory() . '/public/index.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
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
        self::assertSame(0, $exitCode);

        return $output;
    }
}
