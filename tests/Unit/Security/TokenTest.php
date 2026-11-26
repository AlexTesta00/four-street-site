<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Security;

use FourStreet\Security\Fingerprinter;
use FourStreet\Security\IpAddress;
use FourStreet\Security\Token;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Token::class)]
#[CoversClass(Fingerprinter::class)]
#[CoversClass(IpAddress::class)]
final class TokenTest extends TestCase
{
    public function testTokensAreRandomAndUrlSafe(): void
    {
        $first = Token::generate();
        $second = Token::generate();

        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $first->value);
        self::assertNotSame($first->value, $second->value);
        self::assertSame(hash('sha256', $first->value), $first->hash());
        self::assertSame(64, strlen($first->hash()));
    }

    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function receivedValues(): iterable
    {
        yield 'valido' => [str_repeat('aZ09_-', 7) . 'x', true];
        yield 'mancante' => [null, false];
        yield 'vuoto' => ['', false];
        yield 'troppo corto' => [str_repeat('a', 42), false];
        yield 'troppo lungo' => [str_repeat('a', 44), false];
        yield 'con caratteri estranei' => [str_repeat('a', 42) . '=', false];
        yield 'con un a capo' => [str_repeat('a', 42) . "\n", false];
    }

    #[DataProvider('receivedValues')]
    public function testOnlyWellFormedTokensAreAccepted(?string $value, bool $valid): void
    {
        self::assertSame($valid, Token::fromString($value) !== null);
    }

    public function testFingerprintsNeedTheSecretAndDependOnThePurpose(): void
    {
        $fingerprints = new Fingerprinter(str_repeat('s', 32));
        $other = new Fingerprinter(str_repeat('t', 32));

        self::assertSame(hash_hmac('sha256', 'accesso' . "\0" . '203.0.113.7', str_repeat('s', 32)), $fingerprints->fingerprint('accesso', '203.0.113.7'));
        self::assertNotSame($fingerprints->fingerprint('accesso', '203.0.113.7'), $fingerprints->fingerprint('registrazione', '203.0.113.7'));
        self::assertNotSame($fingerprints->fingerprint('accesso', '203.0.113.7'), $other->fingerprint('accesso', '203.0.113.7'));
        self::assertStringNotContainsString('203', $fingerprints->fingerprint('accesso', '203.0.113.7'));
    }

    public function testAShortSecretIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Fingerprinter('corta');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'IPv4' => ['203.0.113.7', '203.0.113.7'];
        yield 'IPv6: la rete /64' => ['2001:db8:85a3:8d3:1319:8a2e:370:7348', '20010db885a308d3/64'];
        yield 'IPv6 nella stessa rete' => ['2001:db8:85a3:8d3::1', '20010db885a308d3/64'];
        yield 'IPv4 scritto come IPv6' => ['::ffff:203.0.113.7', '203.0.113.7'];
        yield 'vuoto' => ['', 'sconosciuto'];
        yield 'non un indirizzo' => ['localhost', 'sconosciuto'];
    }

    #[DataProvider('addresses')]
    public function testTheRateLimitSubjectOfAnAddress(string $address, string $subject): void
    {
        self::assertSame($subject, IpAddress::rateLimitSubject($address));
    }
}
