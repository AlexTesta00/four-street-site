<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Maps;

use FourStreet\Maps\MapLinks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MapLinks::class)]
final class MapLinksTest extends TestCase
{
    private const string ADDRESS = 'Via del Bando 22, San Marino';

    public function testGoogleMapsDirectionsToTheAddress(): void
    {
        self::assertSame(
            'https://www.google.com/maps/dir/?api=1&destination=Via%20del%20Bando%2022%2C%20San%20Marino',
            (new MapLinks(self::ADDRESS))->googleMaps(),
        );
    }

    public function testTheCustomPlaceUrlWinsOverTheAddress(): void
    {
        $links = new MapLinks(self::ADDRESS, 'https://maps.app.goo.gl/AbC123');

        self::assertSame('https://maps.app.goo.gl/AbC123', $links->googleMaps());
        // Apple Mappe usa sempre l'indirizzo.
        self::assertStringContainsString('Via%20del%20Bando', $links->appleMaps());
    }

    public function testAppleMapsDirections(): void
    {
        self::assertSame(
            'https://maps.apple.com/?daddr=Via%20del%20Bando%2022%2C%20San%20Marino',
            (new MapLinks(self::ADDRESS))->appleMaps(),
        );
    }

    public function testEncodesEverySpecialCharacterOfTheAddress(): void
    {
        $links = new MapLinks('Piazza Città & "Borgo" #3/b?');

        self::assertSame(
            'https://maps.apple.com/?daddr=Piazza%20Citt%C3%A0%20%26%20%22Borgo%22%20%233%2Fb%3F',
            $links->appleMaps(),
        );
        self::assertStringEndsWith('destination=Piazza%20Citt%C3%A0%20%26%20%22Borgo%22%20%233%2Fb%3F', $links->googleMaps());
    }
}
