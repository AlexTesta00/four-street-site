<?php

declare(strict_types=1);

namespace FourStreet\Security\RateLimit;

/**
 * Esito di un controllo dei tentativi: si può procedere, oppure fra quanti secondi si potrà.
 */
final readonly class RateLimitStatus
{
    /**
     * @param int $retryAfter secondi alla fine della finestra; 0 se si può procedere
     */
    private function __construct(
        public bool $allowed,
        public int $retryAfter,
    ) {}

    public static function allowed(): self
    {
        return new self(true, 0);
    }

    public static function blocked(int $retryAfter): self
    {
        return new self(false, max(1, $retryAfter));
    }

    /** Il più restrittivo fra due esiti: bloccato se lo è almeno uno, fino alla fine dell'attesa più lunga. */
    public function and(self $other): self
    {
        return match (true) {
            $this->allowed && $other->allowed => $this,
            $this->allowed => $other,
            $other->allowed => $this,
            default => self::blocked(max($this->retryAfter, $other->retryAfter)),
        };
    }

    /** I minuti di attesa, arrotondati per eccesso: "Riprova tra 15 minuti". */
    public function retryAfterMinutes(): int
    {
        return self::minutes($this->retryAfter);
    }

    /** Secondi di attesa in minuti, arrotondati per eccesso e almeno uno. */
    public static function minutes(int $seconds): int
    {
        return max(1, intdiv($seconds + 59, 60));
    }
}
