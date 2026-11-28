<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

/**
 * Com'è andato "Invia le email rimaste": quante email sono partite e, se il giro si è fermato prima della fine, perché.
 * Quante ne restano lo dice la pagina Disdette, che le rilegge: com'è adesso.
 */
final readonly class ResendOutcome
{
    /**
     * @param int $sent le email partite (Sent)
     * @param GymEmailStop|null $stopped perché il giro si è fermato prima della fine (Sent)
     * @param int $retryAfter secondi di attesa (Throttled)
     */
    private function __construct(
        public ResendStatus $status,
        public int $sent = 0,
        public ?GymEmailStop $stopped = null,
        public int $retryAfter = 0,
    ) {}

    public static function sent(GymEmailRound $round): self
    {
        return new self(ResendStatus::Sent, $round->sent(), $round->stopped);
    }

    public static function nothingToSend(): self
    {
        return new self(ResendStatus::NothingToSend);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(ResendStatus::Throttled, retryAfter: $retryAfter);
    }
}
