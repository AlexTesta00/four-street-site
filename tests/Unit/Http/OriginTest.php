<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http;

use FourStreet\Http\Origin;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Origin::class)]
final class OriginTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool, bool}>
     */
    public static function origins(): iterable
    {
        yield 'produzione' => ['https://fourstreet.studio', 'https://fourstreet.studio', true, false];
        yield 'con la barra finale' => ['https://fourstreet.studio/', 'https://fourstreet.studio', true, false];
        yield 'maiuscole' => ['HTTPS://FourStreet.Studio', 'https://fourstreet.studio', true, false];
        yield 'porta predefinita' => ['https://fourstreet.studio:443', 'https://fourstreet.studio', true, false];
        yield 'porta diversa' => ['https://staging.fourstreet.studio:8443', 'https://staging.fourstreet.studio:8443', true, false];
        yield 'sviluppo' => ['http://localhost:8000', 'http://localhost:8000', false, true];
        yield 'smoke test' => ['http://127.0.0.1:8089', 'http://127.0.0.1:8089', false, true];
        yield 'IPv6 locale' => ['http://[::1]:8000', 'http://[::1]:8000', false, true];
    }

    #[DataProvider('origins')]
    public function testReadsAnOrigin(string $url, string $expected, bool $secure, bool $local): void
    {
        $origin = Origin::fromUrl($url);

        self::assertSame($expected, (string) $origin);
        self::assertSame($secure, $origin->isSecure());
        self::assertSame($local, $origin->isLocal());
    }

    public function testBuildsAbsoluteUrls(): void
    {
        self::assertSame(
            'https://fourstreet.studio/account/verifica/?token=abc',
            Origin::fromUrl('https://fourstreet.studio')->url('/account/verifica/?token=abc'),
        );
    }

    public function testAPathMustStartWithASingleSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Origin::fromUrl('https://fourstreet.studio')->url('//evil.example/');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidOrigins(): iterable
    {
        yield 'senza schema' => ['fourstreet.studio', 'indirizzo completo'];
        yield 'altro schema' => ['ftp://fourstreet.studio', 'indirizzo completo'];
        yield 'con un percorso' => ['https://fourstreet.studio/account/', 'senza percorso'];
        yield 'con la query' => ['https://fourstreet.studio/?a=1', 'senza percorso'];
        yield 'con utente' => ['https://mario@fourstreet.studio', 'senza percorso'];
        yield 'http fuori dal computer' => ['http://fourstreet.studio', 'https://'];
        yield 'con spazi' => ['https://fourstreet .studio', 'indirizzo completo'];
    }

    #[DataProvider('invalidOrigins')]
    public function testRejectsAnythingThatIsNotAPublicOrigin(string $url, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Origin::fromUrl($url);
    }
}
