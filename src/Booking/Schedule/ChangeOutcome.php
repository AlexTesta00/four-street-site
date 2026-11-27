<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use LogicException;

/**
 * Com'è andata una modifica dell'orario. Ogni esito porta solo ciò che gli serve.
 */
final readonly class ChangeOutcome
{
    /**
     * @param list<CancelledBooking> $cancelled le prenotazioni disdette (Applied)
     * @param int $retryAfter secondi di attesa (Throttled)
     */
    private function __construct(
        public ChangeStatus $status,
        private ?ChangePlan $plan = null,
        public array $cancelled = [],
        public int $retryAfter = 0,
    ) {}

    /** @param list<CancelledBooking> $cancelled */
    public static function applied(ChangePlan $plan, array $cancelled): self
    {
        return new self(ChangeStatus::Applied, $plan, $cancelled);
    }

    public static function unchanged(ChangePlan $plan): self
    {
        return new self(ChangeStatus::Unchanged, $plan);
    }

    /** @param ChangePlan $plan con le prenotazioni da disdire, da mostrare al titolare */
    public static function needsConfirmation(ChangePlan $plan): self
    {
        return new self(ChangeStatus::NeedsConfirmation, $plan);
    }

    public static function refused(ChangePlan $plan): self
    {
        return new self(ChangeStatus::Refused, $plan);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(ChangeStatus::Throttled, retryAfter: $retryAfter);
    }

    /** Il piano della modifica: c'è per tutti gli esiti tranne Throttled. */
    public function plan(): ChangePlan
    {
        return $this->plan ?? throw new LogicException(sprintf('Nessun piano per l\'esito %s.', $this->status->name));
    }

    /** Perché non si fa (Refused). */
    public function refusal(): ScheduleRefusal
    {
        return $this->plan()->refusal();
    }
}
