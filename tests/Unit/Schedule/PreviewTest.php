<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Schedule;

use FourStreet\Schedule\Preview;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preview::class)]
final class PreviewTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, Preview|null}>
     */
    public static function values(): iterable
    {
        yield 'mappa' => ['mappa', Preview::Map];
        yield 'zero' => ['zero', Preview::Zero];
        yield 'assente' => [null, null];
        yield 'vuota' => ['', null];
        yield 'sconosciuta' => ['altro', null];
        yield 'maiuscole' => ['MAPPA', null];
    }

    #[DataProvider('values')]
    public function testFromQuery(?string $value, ?Preview $expected): void
    {
        self::assertSame($expected, Preview::fromQuery($value));
    }
}
