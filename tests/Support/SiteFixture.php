<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Application;
use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Configurazione uguale a quella di produzione, ma fissata qui: i test non cambiano se cambia config/site.php.
 */
final class SiteFixture
{
    public const string PUBLIC_DIR = '/srv/4street/public';

    public const int CSS_MTIME = 1_764_000_001;
    public const int JS_MTIME = 1_764_000_002;
    public const int LOGO_MTIME = 1_764_000_003;

    /**
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'name' => '4Street',
            'timezone' => 'Europe/Rome',
            'opening' => '2026-12-06 15:00',
            'opening_time_confirmed' => true,
            'address' => 'Via del Bando 22, San Marino',
            'maps_url' => '',
            'links' => [
                'instagram' => 'https://www.instagram.com/4street_sw',
                'whatsapp' => 'https://wa.me/393662546495',
            ],
            'site_url' => 'https://fourstreet.studio',
            'show_placeholders' => true,
            'content' => [
                'hours' => '',
                'prices' => '',
                'room' => '',
                'photos' => ['room' => null, 'entrance' => null, 'detail' => null],
                'privacy_url' => '',
                'company' => '',
            ],
            'booking' => [
                'capacity' => 5,
                'example_slots' => ['07:00' => 2, '09:30' => 4, '12:30' => 1, '17:30' => 5, '18:30' => 3, '20:00' => 0],
                'default_slot' => '18:30',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function config(array $overrides = []): SiteConfig
    {
        return SiteConfig::fromArray([...self::values(), ...$overrides]);
    }

    /**
     * L'applicazione vera, con orologio fermo e date dei file finte.
     *
     * @param array<string, mixed> $overrides
     */
    public static function application(Clock $clock, array $overrides = [], ?CollectingReporter $reporter = null): Application
    {
        return Application::create(
            self::config($overrides),
            self::templateDirectory(),
            self::PUBLIC_DIR,
            $clock,
            new InMemoryMtimeLookup([
                self::PUBLIC_DIR . '/assets/css/app.css' => self::CSS_MTIME,
                self::PUBLIC_DIR . '/assets/js/app.js' => self::JS_MTIME,
                self::PUBLIC_DIR . '/assets/img/logo.jpg' => self::LOGO_MTIME,
            ]),
            $reporter ?? new CollectingReporter(),
        );
    }

    /**
     * Una richiesta all'applicazione vera, con l'orologio fermo all'ora indicata (Europe/Rome).
     *
     * @param array<string, mixed> $overrides
     */
    public static function handle(string $method, string $uri, string $now, array $overrides = []): Response
    {
        return self::application(FrozenClock::at($now), $overrides)->handle(Request::create($method, $uri));
    }

    public static function rootDirectory(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function templateDirectory(): string
    {
        return self::rootDirectory() . '/templates';
    }
}
