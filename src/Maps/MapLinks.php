<?php

declare(strict_types=1);

namespace FourStreet\Maps;

/**
 * Link alle mappe per l'indirizzo della palestra.
 */
final readonly class MapLinks
{
    /**
     * @param string|null $placeUrl scheda Google Maps della palestra, se indicata in config
     */
    public function __construct(
        private string $address,
        private ?string $placeUrl = null,
    ) {}

    /** Google Maps: la scheda della palestra se c'è, altrimenti le indicazioni stradali verso l'indirizzo. */
    public function googleMaps(): string
    {
        return $this->placeUrl ?? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($this->address);
    }

    /** Indicazioni stradali in Apple Mappe. */
    public function appleMaps(): string
    {
        return 'https://maps.apple.com/?daddr=' . rawurlencode($this->address);
    }
}
