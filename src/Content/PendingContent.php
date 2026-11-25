<?php

declare(strict_types=1);

namespace FourStreet\Content;

use FourStreet\Config\ConfigValues;

/**
 * Contenuti che il cliente non ha ancora confermato: orari, prezzi, cosa c'è in sala, le tre foto,
 * l'informativa privacy e i dati della società. Ognuno può mancare.
 * Un contenuto che manca diventa il suo segnaposto tra parentesi quadre ("[Orari da confermare]");
 * con i segnaposto spenti, l'elemento che conterrebbe solo il segnaposto non va in pagina.
 */
final readonly class PendingContent
{
    private const array KEYS = ['hours', 'prices', 'room', 'photos', 'privacy_url', 'company'];

    private const array PHOTO_KEYS = ['room', 'entrance', 'detail'];

    public function __construct(
        public bool $showPlaceholders,
        public ?string $hours = null,
        public ?string $prices = null,
        public ?string $room = null,
        public ?Photo $roomPhoto = null,
        public ?Photo $entrancePhoto = null,
        public ?Photo $detailPhoto = null,
        public ?string $privacyUrl = null,
        public ?string $company = null,
    ) {}

    public static function fromConfig(ConfigValues $content, bool $showPlaceholders): self
    {
        $content->assertKnownKeys(self::KEYS);
        $photos = $content->section('photos');
        $photos->assertKnownKeys(self::PHOTO_KEYS);

        return new self(
            showPlaceholders: $showPlaceholders,
            hours: $content->optionalText('hours'),
            prices: $content->optionalText('prices'),
            room: $content->optionalText('room'),
            roomPhoto: self::photo($photos, 'room'),
            entrancePhoto: self::photo($photos, 'entrance'),
            detailPhoto: self::photo($photos, 'detail'),
            privacyUrl: $content->optionalUrl('privacy_url'),
            company: $content->optionalText('company'),
        );
    }

    /**
     * L'elemento va in pagina: c'è il contenuto, oppure al suo posto si mostra il segnaposto.
     */
    public function shows(string|Photo|null $value): bool
    {
        return $value !== null || $this->showPlaceholders;
    }

    private static function photo(ConfigValues $photos, string $key): ?Photo
    {
        $value = $photos->raw($key);

        return $value === null || $value === [] ? null : Photo::fromConfig($photos->section($key));
    }
}
