<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Security;

use FourStreet\Security\Password\NativePasswordHasher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativePasswordHasher::class)]
final class NativePasswordHasherTest extends TestCase
{
    public function testBcrypt(): void
    {
        $hasher = NativePasswordHasher::bcrypt(4);
        $hash = $hasher->hash('una password lunga');

        self::assertStringStartsWith('$2y$04$', $hash);
        self::assertTrue($hasher->verify('una password lunga', $hash));
        self::assertFalse($hasher->verify('un\'altra password', $hash));
        self::assertFalse($hasher->needsRehash($hash));
        self::assertTrue(NativePasswordHasher::bcrypt(5)->needsRehash($hash));
        self::assertSame(72, $hasher->maxBytes());
    }

    public function testArgon2idWhenAvailable(): void
    {
        if (!NativePasswordHasher::argon2idAvailable()) {
            self::markTestSkipped('Questo PHP non ha Argon2id.');
        }
        $hasher = NativePasswordHasher::argon2id(memoryCost: 8, timeCost: 1);
        $hash = $hasher->hash('una password lunga');

        self::assertStringStartsWith('$argon2id$v=19$m=8,t=1,p=1$', $hash);
        self::assertTrue($hasher->verify('una password lunga', $hash));
        self::assertNull($hasher->maxBytes());
        self::assertTrue(NativePasswordHasher::argon2id(memoryCost: 16, timeCost: 1)->needsRehash($hash));
        self::assertTrue($hasher->needsRehash(NativePasswordHasher::bcrypt(4)->hash('x')), 'Da bcrypt ad Argon2id al prossimo accesso.');
    }

    public function testTheRecommendedAlgorithm(): void
    {
        $hasher = NativePasswordHasher::recommended();

        self::assertSame(NativePasswordHasher::argon2idAvailable() ? null : 72, $hasher->maxBytes());
    }

    public function testTheDummyHashCostsTheSameAndMatchesNothing(): void
    {
        foreach ([NativePasswordHasher::bcrypt(4), ...(NativePasswordHasher::argon2idAvailable() ? [NativePasswordHasher::argon2id(8, 1)] : [])] as $hasher) {
            $dummy = $hasher->dummyHash();
            $real = $hasher->hash('qualsiasi');

            self::assertSame(strlen($real), strlen($dummy));
            self::assertSame(password_get_info($real)['algoName'], password_get_info($dummy)['algoName']);
            self::assertFalse($hasher->needsRehash($dummy), 'Stessi parametri degli hash veri: stesso costo di verifica.');
            self::assertFalse($hasher->verify('', $dummy));
            self::assertFalse($hasher->verify('qualsiasi', $dummy));
        }
    }

    public function testBcryptCostLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NativePasswordHasher::bcrypt(3);
    }
}
