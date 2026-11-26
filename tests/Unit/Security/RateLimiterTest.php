<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Security;

use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Security\Fingerprinter;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Security\RateLimit\RateLimit;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Security\RateLimit\RateLimitStatus;
use FourStreet\Tests\Support\MovableClock;
use FourStreet\Tests\Support\TestDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[Group('database')]
#[CoversClass(RateLimiter::class)]
#[CoversClass(RateLimit::class)]
#[CoversClass(RateLimitStatus::class)]
#[CoversClass(PdoRateLimitStore::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Row::class)]
#[UsesClass(UtcDateTime::class)]
#[UsesClass(Fingerprinter::class)]
final class RateLimiterTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private RateLimiter $limiter;

    private RateLimit $limit;

    protected function setUp(): void
    {
        // 08:00 a Roma, cioè le 07:00 UTC: l'inizio esatto di una finestra di 15 minuti.
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->limiter = new RateLimiter(new PdoRateLimitStore($this->database), new Fingerprinter(str_repeat('s', 32)), $this->clock);
        $this->limit = new RateLimit('prova', 3, 900);
    }

    public function testCountsAttemptsInAFixedWindow(): void
    {
        foreach (range(1, 3) as $attempt) {
            self::assertTrue($this->limiter->hit($this->limit, 'mario')->allowed, sprintf('Tentativo %d.', $attempt));
        }

        $this->clock->travel('+5 minutes');
        $blocked = $this->limiter->hit($this->limit, 'mario');
        self::assertFalse($blocked->allowed);
        self::assertSame(600, $blocked->retryAfter);
        self::assertSame(10, $blocked->retryAfterMinutes());
        // Anche i tentativi respinti contano: si resta fuori fino alla fine della finestra.
        self::assertFalse($this->limiter->hit($this->limit, 'mario')->allowed);

        // Gli altri soggetti hanno il loro contatore.
        self::assertTrue($this->limiter->hit($this->limit, 'anna')->allowed);

        // Nella finestra dopo si riparte da zero.
        $this->clock->travel('+10 minutes');
        self::assertTrue($this->limiter->hit($this->limit, 'mario')->allowed);
    }

    public function testAResetClearsTheSubject(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->limiter->hit($this->limit, 'mario');
        }
        $this->limiter->reset($this->limit, 'mario');

        self::assertTrue($this->limiter->hit($this->limit, 'mario')->allowed);
        self::assertTrue($this->limiter->hit($this->limit, 'mario')->allowed);
        self::assertTrue($this->limiter->hit($this->limit, 'mario')->allowed);
        self::assertFalse($this->limiter->hit($this->limit, 'mario')->allowed);
    }

    public function testLimitsWithTheSameSubjectDoNotMix(): void
    {
        $other = new RateLimit('altro', 1, 900);

        self::assertTrue($this->limiter->hit($other, 'mario')->allowed);
        self::assertFalse($this->limiter->hit($other, 'mario')->allowed);
        self::assertTrue($this->limiter->hit($this->limit, 'mario')->allowed);
    }

    public function testTheSubjectIsStoredOnlyAsAFingerprint(): void
    {
        $this->limiter->hit($this->limit, 'mario@example.com');

        $row = $this->database->fetchOne('SELECT bucket, window_start, hits, expires_at FROM rate_limits');
        self::assertNotNull($row);
        self::assertSame(hash_hmac('sha256', "limite:prova\0mario@example.com", str_repeat('s', 32)), $row->string('bucket'));
        self::assertSame('2026-11-25 07:00:00', $row->string('window_start'));
        self::assertSame('2026-11-25 07:15:00', $row->string('expires_at'));
        self::assertSame(1, $row->int('hits'));
    }

    public function testExpiredWindowsCanBeDeleted(): void
    {
        $this->limiter->hit($this->limit, 'mario');
        $store = new PdoRateLimitStore($this->database);

        self::assertSame(0, $store->deleteExpired($this->clock->now()));
        $this->clock->travel('+15 minutes');
        self::assertSame(1, $store->deleteExpired($this->clock->now()));
    }

    public function testCombinedStatuses(): void
    {
        $allowed = RateLimitStatus::allowed();
        $short = RateLimitStatus::blocked(60);
        $long = RateLimitStatus::blocked(600);

        self::assertTrue($allowed->and($allowed)->allowed);
        self::assertSame($short, $allowed->and($short));
        self::assertSame($long, $long->and($allowed));
        self::assertSame(600, $short->and($long)->retryAfter);
        self::assertSame(1, RateLimitStatus::blocked(0)->retryAfter);
        self::assertSame(1, RateLimitStatus::minutes(1));
        self::assertSame(2, RateLimitStatus::minutes(61));
    }

    public function testInvalidLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimit('Prova con spazi', 1, 60);
    }

    public function testALimitNeedsAttemptsAndADuration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimit('prova', 0, 60);
    }
}
