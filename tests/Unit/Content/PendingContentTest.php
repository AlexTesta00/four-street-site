<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Content;

use FourStreet\Config\ConfigValues;
use FourStreet\Config\InvalidSiteConfig;
use FourStreet\Content\PendingContent;
use FourStreet\Content\Photo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PendingContent::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(InvalidSiteConfig::class)]
#[UsesClass(Photo::class)]
final class PendingContentTest extends TestCase
{
    public function testEverythingCanBeMissing(): void
    {
        $content = PendingContent::fromConfig(ConfigValues::root([]), true);

        self::assertTrue($content->showPlaceholders);
        self::assertNull($content->hours);
        self::assertNull($content->prices);
        self::assertNull($content->room);
        self::assertNull($content->roomPhoto);
        self::assertNull($content->entrancePhoto);
        self::assertNull($content->detailPhoto);
        self::assertNull($content->privacyUrl);
        self::assertNull($content->company);
    }

    public function testEmptyValuesAreMissing(): void
    {
        $content = PendingContent::fromConfig(ConfigValues::root([
            'hours' => '',
            'prices' => ' ',
            'room' => '',
            'photos' => ['room' => null, 'entrance' => [], 'detail' => null],
            'privacy_url' => '',
            'company' => '',
        ]), false);

        self::assertFalse($content->showPlaceholders);
        self::assertNull($content->prices);
        self::assertNull($content->entrancePhoto);
    }

    public function testReadsTheContentOnceItArrives(): void
    {
        $content = PendingContent::fromConfig(ConfigValues::root([
            'hours' => 'Dal lunedì al sabato, 7:00-22:00',
            'prices' => 'Su richiesta',
            'room' => 'pesi liberi e rack',
            'photos' => [
                'room' => ['src' => '/assets/img/sala.jpg', 'width' => 1600, 'height' => 1200, 'alt' => 'La sala.'],
                'detail' => ['src' => '/assets/img/dettaglio.webp', 'width' => 800, 'height' => 1000, 'alt' => 'Un dettaglio.'],
            ],
            'privacy_url' => 'https://fourstreet.studio/privacy/',
            'company' => '4Street S.r.l.',
        ]), true);

        self::assertSame('Dal lunedì al sabato, 7:00-22:00', $content->hours);
        self::assertSame('Su richiesta', $content->prices);
        self::assertSame('pesi liberi e rack', $content->room);
        self::assertSame('/assets/img/sala.jpg', $content->roomPhoto?->src);
        self::assertNull($content->entrancePhoto);
        self::assertSame(1000, $content->detailPhoto?->height);
        self::assertSame('https://fourstreet.studio/privacy/', $content->privacyUrl);
        self::assertSame('4Street S.r.l.', $content->company);
    }

    public function testWhatGoesOnThePage(): void
    {
        $photo = new Photo('/assets/img/sala.jpg', 10, 10, 'La sala.');
        $withPlaceholders = new PendingContent(true);
        $withoutPlaceholders = new PendingContent(false);

        // Un contenuto presente c'è sempre; uno che manca solo come segnaposto.
        self::assertTrue($withPlaceholders->shows('Su richiesta'));
        self::assertTrue($withPlaceholders->shows($photo));
        self::assertTrue($withPlaceholders->shows(null));
        self::assertTrue($withoutPlaceholders->shows('Su richiesta'));
        self::assertTrue($withoutPlaceholders->shows($photo));
        self::assertFalse($withoutPlaceholders->shows(null));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidContent(): iterable
    {
        yield 'chiave sconosciuta' => [['orari' => 'x'], 'chiavi sconosciute (orari)'];
        yield 'foto sconosciuta' => [['photos' => ['sala' => null]], 'chiavi sconosciute in "photos" (sala)'];
        yield 'orari su più righe' => [['hours' => "Lun\nMar"], '"hours" deve stare su una riga'];
        yield 'prezzi non testo' => [['prices' => 100], '"prices" deve essere un testo, oppure vuoto (trovato: int)'];
        yield 'privacy senza schema' => [['privacy_url' => 'fourstreet.studio/privacy'], '"privacy_url" deve essere vuoto oppure un indirizzo completo'];
        yield 'foto come testo' => [['photos' => ['room' => '/assets/img/sala.jpg']], '"photos.room" deve essere un elenco chiave => valore'];
        yield 'foto incompleta' => [['photos' => ['room' => ['src' => '/assets/img/sala.jpg']]], '"photos.room.width" deve essere un numero intero maggiore di zero'];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('invalidContent')]
    public function testInvalidContentStopsTheSite(array $values, string $message): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage($message);

        PendingContent::fromConfig(ConfigValues::root($values), true);
    }
}
