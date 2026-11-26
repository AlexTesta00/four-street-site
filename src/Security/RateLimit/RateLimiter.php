<?php

declare(strict_types=1);

namespace FourStreet\Security\RateLimit;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Clock\Clock;
use FourStreet\Security\Fingerprinter;
use SensitiveParameter;

/**
 * Limita i tentativi a finestre fisse. Le finestre sono allineate all'epoca Unix (una finestra di 15 minuti
 * va da :00 a :15, da :15 a :30…). Il soggetto (un IP, un'email) non si salva mai: solo la sua impronta.
 * C'è solo hit(), che conta e risponde in un passo: un controllo separato dal conteggio lascerebbe passare
 * insieme tutte le richieste arrivate prima che la prima venga contata.
 */
final readonly class RateLimiter
{
    public function __construct(
        private RateLimitStore $store,
        private Fingerprinter $fingerprints,
        private Clock $clock,
    ) {}

    /** Conta un tentativo e dice se rientra nel limite. */
    public function hit(RateLimit $limit, #[SensitiveParameter] string $subject): RateLimitStatus
    {
        [$start, $end] = $this->window($limit);
        $hits = $this->store->increment($this->bucket($limit, $subject), $start, $end);

        return $hits <= $limit->maxAttempts ? RateLimitStatus::allowed() : $this->blocked($end);
    }

    /** Azzera i tentativi del soggetto. */
    public function reset(RateLimit $limit, #[SensitiveParameter] string $subject): void
    {
        $this->store->clear($this->bucket($limit, $subject));
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable} inizio e fine della finestra corrente, in UTC
     */
    private function window(RateLimit $limit): array
    {
        $now = $this->clock->now()->getTimestamp();
        $start = intdiv($now, $limit->windowSeconds) * $limit->windowSeconds;
        $utc = new DateTimeZone('UTC');

        return [
            (new DateTimeImmutable('@' . $start))->setTimezone($utc),
            (new DateTimeImmutable('@' . ($start + $limit->windowSeconds)))->setTimezone($utc),
        ];
    }

    private function blocked(DateTimeImmutable $windowEnd): RateLimitStatus
    {
        return RateLimitStatus::blocked($windowEnd->getTimestamp() - $this->clock->now()->getTimestamp());
    }

    private function bucket(RateLimit $limit, string $subject): string
    {
        return $this->fingerprints->fingerprint('limite:' . $limit->name, $subject);
    }
}
