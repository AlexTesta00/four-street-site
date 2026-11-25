<?php

declare(strict_types=1);

namespace FourStreet\Content;

use FourStreet\Config\ConfigValues;
use InvalidArgumentException;

/**
 * Una fotografia del sito: un file in public/assets/img/, le sue misure in pixel e il testo alternativo.
 * Le misure finiscono negli attributi width e height, così la pagina non si sposta mentre l'immagine arriva.
 */
final readonly class Photo
{
    private const string SRC_PATTERN = '#\A/assets/img/[a-z0-9][a-z0-9_-]*\.(?:jpe?g|png|webp|avif)\z#';

    private const array KEYS = ['src', 'width', 'height', 'alt'];

    public function __construct(
        public string $src,
        public int $width,
        public int $height,
        public string $alt,
    ) {
        if (!self::isValidSrc($src)) {
            throw new InvalidArgumentException(sprintf('Percorso di foto non valido: "%s".', $src));
        }
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(sprintf('Misure di foto non valide: %d×%d.', $width, $height));
        }
        if (trim($alt) === '') {
            throw new InvalidArgumentException('Una foto ha sempre un testo alternativo.');
        }
    }

    /**
     * Foto da config/site.php: ['src' => '/assets/img/sala.jpg', 'width' => 1600, 'height' => 1200, 'alt' => '…'].
     */
    public static function fromConfig(ConfigValues $photo): self
    {
        $photo->assertKnownKeys(self::KEYS);
        $src = $photo->text('src');
        if (!self::isValidSrc($src)) {
            throw $photo->invalid('src', 'deve essere un file .jpg, .png, .webp o .avif in /assets/img/, per esempio "/assets/img/sala.jpg"', $src);
        }

        return new self($src, $photo->positiveInt('width'), $photo->positiveInt('height'), $photo->text('alt'));
    }

    private static function isValidSrc(string $src): bool
    {
        return preg_match(self::SRC_PATTERN, $src) === 1;
    }
}
