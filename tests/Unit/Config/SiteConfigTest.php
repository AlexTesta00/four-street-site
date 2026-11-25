<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Config;

use FourStreet\Config\InvalidSiteConfig;
use FourStreet\Config\SiteConfig;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteConfig::class)]
#[CoversClass(InvalidSiteConfig::class)]
final class SiteConfigTest extends TestCase
{
    public function testReadsAValidConfiguration(): void
    {
        $config = SiteFixture::config();

        self::assertSame('4Street', $config->name);
        self::assertSame('Europe/Rome', $config->timezone->getName());
        self::assertSame('2026-12-06 15:00:00 Europe/Rome', $config->opening->format('Y-m-d H:i:s e'));
        self::assertTrue($config->openingTimeConfirmed);
        self::assertSame('Via del Bando 22, San Marino', $config->address);
        self::assertNull($config->mapsUrl);
        self::assertSame('https://www.instagram.com/4street_sw', $config->link('instagram'));
        self::assertSame('https://wa.me/393662546495', $config->link('whatsapp'));
        self::assertNull($config->link('tiktok'));
        self::assertSame('https://fourstreet.studio', $config->siteUrl);
    }

    public function testTheOpeningIsInTheConfiguredTimezoneNotTheMachineOne(): void
    {
        $config = SiteFixture::config(['timezone' => 'America/New_York']);

        self::assertSame('2026-12-06T15:00:00-05:00', $config->opening->format(DATE_ATOM));
    }

    public function testOpeningTimeOnlyWhenConfirmed(): void
    {
        self::assertSame('15:00', SiteFixture::config()->openingTime());
        self::assertNull(SiteFixture::config(['opening_time_confirmed' => false])->openingTime());
    }

    public function testEmptyLinksAreNotPublished(): void
    {
        $config = SiteFixture::config(['links' => ['instagram' => '', 'whatsapp' => 'https://wa.me/1']]);

        self::assertSame(['whatsapp' => 'https://wa.me/1'], $config->links);
        self::assertNull($config->link('instagram'));
    }

    public function testOptionalValuesCanBeEmptyOrMissing(): void
    {
        $values = SiteFixture::values();
        unset($values['maps_url'], $values['links'], $values['site_url']);
        $config = SiteConfig::fromArray($values);

        self::assertNull($config->mapsUrl);
        self::assertSame([], $config->links);
        self::assertNull($config->siteUrl);
        self::assertNull(SiteFixture::config(['site_url' => ''])->siteUrl);
    }

    public function testKeepsTheCustomMapsUrlAndDropsTheTrailingSlashOfTheSiteUrl(): void
    {
        $config = SiteFixture::config([
            'maps_url' => 'https://maps.app.goo.gl/AbC123',
            'site_url' => 'https://fourstreet.studio/',
        ]);

        self::assertSame('https://maps.app.goo.gl/AbC123', $config->mapsUrl);
        self::assertSame('https://fourstreet.studio', $config->siteUrl);
    }

    public function testTheRealConfigurationFileIsValid(): void
    {
        $config = SiteConfig::fromFile(SiteFixture::rootDirectory() . '/config/site.php');

        self::assertNotSame('', $config->address);
    }

    public function testFailsWhenTheFileIsMissing(): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('File di configurazione non trovato');

        SiteConfig::fromFile(__DIR__ . '/non-esiste.php');
    }

    public function testFailsWhenTheFileDoesNotReturnAnArray(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'site');
        self::assertIsString($file);
        file_put_contents($file, "<?php\nreturn 'no';\n");

        try {
            $this->expectException(InvalidSiteConfig::class);
            $this->expectExceptionMessage('deve restituire un array');

            SiteConfig::fromFile($file);
        } finally {
            unlink($file);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'chiave sconosciuta' => [['adress' => 'x'], 'chiavi sconosciute (adress)'];
        yield 'nome vuoto' => [['name' => '  '], '"name" deve essere un testo non vuoto'];
        yield 'nome non testo' => [['name' => 4], '"name" deve essere un testo non vuoto (trovato: int)'];
        yield 'indirizzo vuoto' => [['address' => ''], '"address" deve essere un testo non vuoto'];
        yield 'indirizzo su due righe' => [['address' => "Via del Bando 22\nSan Marino"], '"address" deve stare su una riga'];
        yield 'fuso inesistente' => [['timezone' => 'Europe/Atlantide'], '"timezone" deve essere un fuso orario'];
        yield 'fuso come abbreviazione' => [['timezone' => 'CET'], '"timezone" deve essere un fuso orario'];
        yield 'data senza ora' => [['opening' => '2026-12-06'], '"opening" deve essere una data e ora esistenti'];
        yield 'data con secondi' => [['opening' => '2026-12-06 15:00:00'], '"opening" deve essere una data e ora esistenti'];
        yield 'data scritta all\'italiana' => [['opening' => '06/12/2026 15:00'], '"opening" deve essere una data e ora esistenti'];
        yield 'giorno inesistente' => [['opening' => '2026-02-30 15:00'], '"opening" deve essere una data e ora esistenti nel formato AAAA-MM-GG HH:MM (trovato: "2026-02-30 15:00")'];
        yield 'ora saltata dal cambio dell\'ora' => [['opening' => '2026-03-29 02:30'], '"opening" deve essere una data e ora esistenti'];
        yield 'conferma come testo' => [['opening_time_confirmed' => 'si'], '"opening_time_confirmed" deve essere true oppure false (trovato: "si")'];
        yield 'mappa non web' => [['maps_url' => 'ftp://example.com/x'], '"maps_url" deve essere vuoto oppure un indirizzo completo'];
        yield 'mappa senza schema' => [['maps_url' => 'maps.google.com/x'], '"maps_url" deve essere vuoto oppure un indirizzo completo'];
        yield 'mappa con spazi' => [['maps_url' => 'https://maps.google.com/a b'], '"maps_url" deve essere vuoto oppure un indirizzo completo'];
        yield 'link non elenco' => [['links' => 'https://example.com'], '"links" deve essere un elenco'];
        yield 'link senza nome' => [['links' => ['https://example.com']], '"links" deve avere un nome per ogni link'];
        yield 'link non valido' => [['links' => ['instagram' => 'instagram.com/4street']], '"links.instagram" deve essere vuoto oppure un indirizzo completo'];
        yield 'link non testo' => [['links' => ['instagram' => true]], '"links.instagram" deve essere vuoto oppure un indirizzo completo'];
        yield 'sito non valido' => [['site_url' => 'javascript:alert(1)'], '"site_url" deve essere vuoto oppure un indirizzo completo'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidValues')]
    public function testFailsFastWithAClearMessage(array $overrides, string $message): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage($message);

        SiteFixture::config($overrides);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredKeys(): iterable
    {
        foreach (['name', 'timezone', 'opening', 'opening_time_confirmed', 'address'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('requiredKeys')]
    public function testRequiredKeysCannotBeMissing(string $key): void
    {
        $values = SiteFixture::values();
        unset($values[$key]);

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('"' . $key . '"');

        SiteConfig::fromArray($values);
    }
}
