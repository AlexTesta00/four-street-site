<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Error;

use FourStreet\Error\ErrorLogReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ErrorLogReporter::class)]
final class ErrorLogReporterTest extends TestCase
{
    public function testWritesTheErrorToThePhpLog(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'log');
        self::assertIsString($log);
        $previous = ini_set('error_log', $log);

        try {
            (new ErrorLogReporter())->report(new RuntimeException('Qualcosa si è rotto.'));
            $written = (string) file_get_contents($log);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            unlink($log);
        }

        self::assertStringContainsString('[4Street] RuntimeException: Qualcosa si è rotto.', $written);
        self::assertStringContainsString(__FILE__, $written, 'Il log contiene dove è nato l\'errore.');
    }
}
