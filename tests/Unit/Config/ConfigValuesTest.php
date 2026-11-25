<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Config;

use Closure;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\InvalidSiteConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigValues::class)]
#[CoversClass(InvalidSiteConfig::class)]
final class ConfigValuesTest extends TestCase
{
    public function testReadsValidValues(): void
    {
        $values = ConfigValues::root([
            'name' => '4Street',
            'note' => '',
            'flag' => false,
            'count' => 5,
            'url' => 'https://fourstreet.studio',
        ]);

        self::assertSame('4Street', $values->text('name'));
        self::assertNull($values->optionalText('note'));
        self::assertNull($values->optionalText('missing'));
        self::assertFalse($values->flag('flag'));
        self::assertSame(5, $values->positiveInt('count'));
        self::assertSame('https://fourstreet.studio', $values->optionalUrl('url'));
        self::assertNull($values->optionalUrl('missing'));
        self::assertSame(5, $values->raw('count'));
        self::assertNull($values->raw('missing'));
    }

    public function testABlankOptionalTextIsMissing(): void
    {
        self::assertNull(ConfigValues::root(['note' => '   '])->optionalText('note'));
        self::assertSame(' testo ', ConfigValues::root(['note' => ' testo '])->optionalText('note'));
    }

    public function testSectionsNameTheFullKeyInTheirErrors(): void
    {
        $booking = ConfigValues::root(['booking' => ['capacity' => 0]])->section('booking');

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('Configurazione del sito non valida: "booking.capacity" deve essere un numero intero maggiore di zero (trovato: "0").');

        $booking->positiveInt('capacity');
    }

    public function testAMissingSectionIsEmpty(): void
    {
        $section = ConfigValues::root([])->section('content');

        self::assertNull($section->optionalText('hours'));
        self::assertNull($section->section('photos')->raw('room'));
    }

    public function testUnknownKeys(): void
    {
        ConfigValues::root(['name' => 'x'])->assertKnownKeys(['name', 'address']);

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('Configurazione del sito non valida: chiavi sconosciute (adress, 0). Valori ammessi: name, address.');

        ConfigValues::root(['adress' => 'x', 'name' => 'y', 0 => 'z'])->assertKnownKeys(['name', 'address']);
    }

    public function testUnknownKeysOfASection(): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('chiavi sconosciute in "content.photos" (sala). Valori ammessi: room.');

        ConfigValues::root(['content' => ['photos' => ['sala' => null]]])->section('content')->section('photos')->assertKnownKeys(['room']);
    }

    /**
     * @return iterable<string, array{Closure(ConfigValues): mixed, array<string, mixed>, string}>
     */
    public static function invalidValues(): iterable
    {
        $text = static fn(ConfigValues $values): string => $values->text('key');
        $optionalText = static fn(ConfigValues $values): ?string => $values->optionalText('key');
        $flag = static fn(ConfigValues $values): bool => $values->flag('key');
        $positiveInt = static fn(ConfigValues $values): int => $values->positiveInt('key');
        $optionalUrl = static fn(ConfigValues $values): ?string => $values->optionalUrl('key');
        $section = static fn(ConfigValues $values): ConfigValues => $values->section('key');
        $requiredSection = static fn(ConfigValues $values): ConfigValues => $values->requiredSection('key');

        yield 'testo mancante' => [$text, [], '"key" deve essere un testo non vuoto (trovato: null)'];
        yield 'testo vuoto' => [$text, ['key' => ' '], '"key" deve essere un testo non vuoto (trovato: " ")'];
        yield 'testo su due righe' => [$text, ['key' => "a\nb"], '"key" deve stare su una riga, senza caratteri di controllo'];
        yield 'testo facoltativo non testo' => [$optionalText, ['key' => 3], '"key" deve essere un testo, oppure vuoto (trovato: int)'];
        yield 'testo facoltativo con tabulazione' => [$optionalText, ['key' => "a\tb"], '"key" deve stare su una riga'];
        yield 'flag come testo' => [$flag, ['key' => 'true'], '"key" deve essere true oppure false (trovato: "true")'];
        yield 'numero zero' => [$positiveInt, ['key' => 0], '"key" deve essere un numero intero maggiore di zero (trovato: "0")'];
        yield 'numero negativo' => [$positiveInt, ['key' => -2], '(trovato: "-2")'];
        yield 'numero come testo' => [$positiveInt, ['key' => '5'], '(trovato: "5")'];
        yield 'numero decimale' => [$positiveInt, ['key' => 1.5], '(trovato: float)'];
        yield 'indirizzo senza schema' => [$optionalUrl, ['key' => 'fourstreet.studio'], '"key" deve essere vuoto oppure un indirizzo completo http(s)://…'];
        yield 'sezione non elenco' => [$section, ['key' => 'x'], '"key" deve essere un elenco chiave => valore (trovato: "x")'];
        yield 'sezione obbligatoria mancante' => [$requiredSection, [], '"key" deve essere un elenco chiave => valore (trovato: null)'];
        yield 'sezione obbligatoria non elenco' => [$requiredSection, ['key' => 5], '"key" deve essere un elenco chiave => valore (trovato: int)'];
    }

    /**
     * @param Closure(ConfigValues): mixed $read
     * @param array<string, mixed> $values
     */
    #[DataProvider('invalidValues')]
    public function testFailsWithAClearMessage(Closure $read, array $values, string $message): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage($message);

        $read(ConfigValues::root($values));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function urls(): iterable
    {
        yield 'https' => ['https://fourstreet.studio/privacy/', true];
        yield 'http' => ['http://example.com', true];
        yield 'schema in maiuscolo' => ['HTTPS://example.com', true];
        yield 'senza schema' => ['example.com', false];
        yield 'senza host' => ['https:///percorso', false];
        yield 'altro schema' => ['javascript:alert(1)', false];
        yield 'con spazi' => ['https://example.com/a b', false];
        yield 'con a capo' => ["https://example.com/\n", false];
    }

    #[DataProvider('urls')]
    public function testWebUrls(string $url, bool $valid): void
    {
        self::assertSame($valid, ConfigValues::isWebUrl($url));
    }
}
