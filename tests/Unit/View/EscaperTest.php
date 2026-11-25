<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\View;

use FourStreet\View\Escaper;
use FourStreet\View\Html;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Escaper::class)]
#[CoversClass(Html::class)]
final class EscaperTest extends TestCase
{
    /**
     * @return iterable<string, array{Html|string|int|null, string}>
     */
    public static function values(): iterable
    {
        yield 'testo semplice' => ['Via del Bando 22, San Marino', 'Via del Bando 22, San Marino'];
        yield 'tag' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'];
        yield 'e commerciale' => ['a=1&b=2', 'a=1&amp;b=2'];
        yield 'virgolette doppie' => ['"x"', '&quot;x&quot;'];
        yield 'apostrofo' => ["l'inaugurazione", 'l&#039;inaugurazione'];
        yield 'caratteri non ASCII invariati' => ['Città · più', 'Città · più'];
        yield 'UTF-8 non valido sostituito' => ["a\xC3b", "a\u{FFFD}b"];
        yield 'numero' => [1_764_000_001, '1764000001'];
        yield 'null' => [null, ''];
        yield 'markup già sicuro' => [new Html('<b>ok</b>'), '<b>ok</b>'];
    }

    #[DataProvider('values')]
    public function testEscapesForHtmlTextAndAttributes(Html|string|int|null $value, string $expected): void
    {
        self::assertSame($expected, Escaper::html($value));
    }

    public function testHtmlIsNeverEscapedTwice(): void
    {
        $once = new Html(Escaper::html('a & b'));

        self::assertSame('a &amp; b', Escaper::html($once));
        self::assertSame('a &amp; b', (string) $once);
    }
}
