<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Content;

use FourStreet\Config\ConfigValues;
use FourStreet\Config\InvalidSiteConfig;
use FourStreet\Content\Photo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Photo::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(InvalidSiteConfig::class)]
final class PhotoTest extends TestCase
{
    private const array VALID = ['src' => '/assets/img/sala.jpg', 'width' => 1600, 'height' => 1200, 'alt' => 'La sala, in bianco e nero.'];

    public function testReadsAPhotoFromTheConfiguration(): void
    {
        $photo = Photo::fromConfig(ConfigValues::root(self::VALID));

        self::assertSame('/assets/img/sala.jpg', $photo->src);
        self::assertSame(1600, $photo->width);
        self::assertSame(1200, $photo->height);
        self::assertSame('La sala, in bianco e nero.', $photo->alt);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedFiles(): iterable
    {
        yield 'jpg' => ['/assets/img/sala.jpg'];
        yield 'jpeg' => ['/assets/img/sala-2.jpeg'];
        yield 'png' => ['/assets/img/ingresso_notte.png'];
        yield 'webp' => ['/assets/img/dettaglio.webp'];
        yield 'avif' => ['/assets/img/sala.avif'];
    }

    #[DataProvider('acceptedFiles')]
    public function testAcceptsTheWebImageFormats(string $src): void
    {
        self::assertSame($src, (new Photo($src, 10, 10, 'Foto'))->src);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPhotos(): iterable
    {
        yield 'fuori da assets/img' => [['src' => '/sala.jpg'], '"src" deve essere un file .jpg, .png, .webp o .avif in /assets/img/'];
        yield 'risalita di cartella' => [['src' => '/assets/img/../../config/site.php'], '"src" deve essere un file'];
        yield 'indirizzo esterno' => [['src' => 'https://example.com/sala.jpg'], '"src" deve essere un file'];
        yield 'formato non adatto' => [['src' => '/assets/img/sala.pdf'], '"src" deve essere un file'];
        yield 'larghezza zero' => [['width' => 0], '"width" deve essere un numero intero maggiore di zero'];
        yield 'altezza come testo' => [['height' => '1200'], '"height" deve essere un numero intero maggiore di zero'];
        yield 'senza testo alternativo' => [['alt' => ''], '"alt" deve essere un testo non vuoto'];
        yield 'chiave sconosciuta' => [['title' => 'x'], 'chiavi sconosciute (title)'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidPhotos')]
    public function testInvalidPhotosStopTheSite(array $overrides, string $message): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage($message);

        Photo::fromConfig(ConfigValues::root([...self::VALID, ...$overrides]));
    }

    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function brokenInvariants(): iterable
    {
        yield 'percorso' => ['sala.jpg', 10, 10, 'Foto'];
        yield 'larghezza' => ['/assets/img/sala.jpg', 0, 10, 'Foto'];
        yield 'altezza' => ['/assets/img/sala.jpg', 10, -1, 'Foto'];
        yield 'testo alternativo' => ['/assets/img/sala.jpg', 10, 10, ' '];
    }

    #[DataProvider('brokenInvariants')]
    public function testThePhotoItselfRejectsInvalidValues(string $src, int $width, int $height, string $alt): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Photo($src, $width, $height, $alt);
    }
}
