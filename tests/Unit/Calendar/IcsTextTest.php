<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Calendar;

use FourStreet\Calendar\IcsText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IcsText::class)]
final class IcsTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'testo semplice' => ['Inaugurazione 4Street', 'Inaugurazione 4Street'];
        yield 'virgola' => ['Via del Bando 22, San Marino', 'Via del Bando 22\, San Marino'];
        yield 'punto e virgola' => ['a;b', 'a\;b'];
        yield 'barra rovesciata' => ['a\b', 'a\\\b'];
        yield 'barra seguita da virgola' => ['a\,b', 'a\\\\\,b'];
        yield 'a capo' => ["riga 1\nriga 2", 'riga 1\nriga 2'];
        yield 'a capo di Windows' => ["riga 1\r\nriga 2", 'riga 1\nriga 2'];
        yield 'a capo vecchio Mac' => ["riga 1\rriga 2", 'riga 1\nriga 2'];
        yield 'caratteri non ASCII' => ['Città · più', 'Città · più'];
        yield 'due punti e virgolette restano' => ['Ore: "15:00"', 'Ore: "15:00"'];
    }

    #[DataProvider('texts')]
    public function testEscape(string $text, string $expected): void
    {
        self::assertSame($expected, IcsText::escape($text));
    }

    public function testShortLinesAreNotFolded(): void
    {
        $line = str_repeat('a', 75);

        self::assertSame([$line], IcsText::fold($line));
        self::assertSame([''], IcsText::fold(''));
    }

    public function testLongLinesAreFoldedAt75Octets(): void
    {
        $line = 'LOCATION:' . str_repeat('x', 200);
        $parts = IcsText::fold($line);

        self::assertCount(3, $parts);
        foreach ($parts as $index => $part) {
            self::assertLessThanOrEqual(75, strlen($part));
            if ($index > 0) {
                self::assertStringStartsWith(' ', $part);
            }
        }
        self::assertSame(75, strlen($parts[0]));
        self::assertSame(75, strlen($parts[1]));
        self::assertSame($line, self::unfold($parts));
    }

    public function testFoldingNeverSplitsAMultibyteCharacter(): void
    {
        // "à" è di due byte: con 74 byte prima, il taglio a 75 cadrebbe a metà.
        $line = str_repeat('a', 74) . str_repeat('à', 10) . '🏋️' . str_repeat('é', 40);
        $parts = IcsText::fold($line);

        self::assertSame(74, strlen($parts[0]));
        foreach ($parts as $part) {
            self::assertLessThanOrEqual(75, strlen($part));
            self::assertTrue(mb_check_encoding($part, 'UTF-8'), 'Ogni riga deve essere UTF-8 valido.');
        }
        self::assertSame($line, self::unfold($parts));
    }

    /**
     * @param list<string> $parts
     */
    private static function unfold(array $parts): string
    {
        return implode('', array_map(static fn(string $part, int $index): string => $index === 0 ? $part : substr($part, 1), $parts, array_keys($parts)));
    }
}
