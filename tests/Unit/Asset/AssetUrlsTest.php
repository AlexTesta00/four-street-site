<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Asset;

use FourStreet\Asset\AssetUrls;
use FourStreet\Tests\Support\InMemoryMtimeLookup;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssetUrls::class)]
final class AssetUrlsTest extends TestCase
{
    public function testAddsTheModificationTimeAsVersion(): void
    {
        $mtimes = new InMemoryMtimeLookup(['/srv/public/assets/css/app.css' => 1_764_000_001]);

        self::assertSame('/assets/css/app.css?v=1764000001', (new AssetUrls('/srv/public', $mtimes))->url('/assets/css/app.css'));
        self::assertSame(['/srv/public/assets/css/app.css'], $mtimes->asked());
    }

    public function testWithoutTheFileTheUrlHasNoVersion(): void
    {
        self::assertSame('/assets/js/app.js', (new AssetUrls('/srv/public', new InMemoryMtimeLookup()))->url('/assets/js/app.js'));
    }

    public function testATrailingSeparatorInThePublicDirectoryIsIgnored(): void
    {
        $mtimes = new InMemoryMtimeLookup(['C:\site\public/assets/img/logo.jpg' => 7]);

        self::assertSame('/assets/img/logo.jpg?v=7', (new AssetUrls('C:\site\public\\', $mtimes))->url('/assets/img/logo.jpg'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'senza barra iniziale' => ['assets/css/app.css'];
        yield 'risalita di cartella' => ['/assets/../../config/site.php'];
        yield 'risalita finale' => ['/assets/..'];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsPathsOutsideThePublicDirectory(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AssetUrls('/srv/public', new InMemoryMtimeLookup()))->url($path);
    }
}
