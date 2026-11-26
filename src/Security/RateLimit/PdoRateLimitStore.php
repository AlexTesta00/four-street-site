<?php

declare(strict_types=1);

namespace FourStreet\Security\RateLimit;

use DateTimeImmutable;
use FourStreet\Database\Database;
use FourStreet\Database\Driver;
use FourStreet\Database\UtcDateTime;

/**
 * Contatori nella tabella rate_limits: una riga per impronta e finestra, incrementata con un upsert
 * atomico (due richieste insieme non perdono mai un tentativo).
 */
final readonly class PdoRateLimitStore implements RateLimitStore
{
    public function __construct(
        private Database $database,
    ) {}

    public function increment(string $bucket, DateTimeImmutable $windowStart, DateTimeImmutable $expiresAt): int
    {
        $this->database->execute(
            'INSERT INTO rate_limits (bucket, window_start, hits, expires_at) VALUES (:bucket, :window_start, 1, :expires_at) '
            . match ($this->database->driver()) {
                Driver::Sqlite => 'ON CONFLICT (bucket, window_start) DO UPDATE SET hits = hits + 1',
                Driver::MySql => 'ON DUPLICATE KEY UPDATE hits = hits + 1',
            },
            [
                'bucket' => $bucket,
                'window_start' => UtcDateTime::format($windowStart),
                'expires_at' => UtcDateTime::format($expiresAt),
            ],
        );

        $row = $this->database->fetchOne(
            'SELECT hits FROM rate_limits WHERE bucket = :bucket AND window_start = :window_start',
            ['bucket' => $bucket, 'window_start' => UtcDateTime::format($windowStart)],
        );

        // La riga l'ha appena scritta l'upsert; manca solo se un clear() nello stesso istante l'ha già
        // azzerata, e allora c'è soltanto questo tentativo.
        return $row === null ? 1 : $row->int('hits');
    }

    public function clear(string $bucket): void
    {
        $this->database->execute('DELETE FROM rate_limits WHERE bucket = :bucket', ['bucket' => $bucket]);
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        return $this->database->execute('DELETE FROM rate_limits WHERE expires_at <= :now', ['now' => UtcDateTime::format($now)]);
    }
}
