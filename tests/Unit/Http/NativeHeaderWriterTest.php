<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http;

use FourStreet\Http\NativeHeaderWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Da riga di comando PHP ignora le intestazioni, ma lo stato della risposta si può leggere.
 * Le intestazioni vere si vedono solo con un server (php -S e curl).
 */
#[CoversClass(NativeHeaderWriter::class)]
final class NativeHeaderWriterTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSetsTheStatusCode(): void
    {
        $writer = new NativeHeaderWriter();
        $writer->status(404);
        $writer->header('X-Test: a', true);
        $writer->remove('X-Powered-By');

        self::assertSame(404, http_response_code());
        self::assertFalse($writer->headersSent());
    }
}
