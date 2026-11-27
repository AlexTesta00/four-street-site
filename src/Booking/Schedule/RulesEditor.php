<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Account\User;
use FourStreet\Booking\Staff\StaffThrottle;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;

/**
 * Le regole delle prenotazioni che il titolare cambia dal pannello: si salvano tutte insieme, e da lì valgono al posto
 * di quelle del file. Non toccano le prenotazioni già fatte: meno giorni prenotabili o meno prenotazioni future
 * valgono per le prossime, chi ha già prenotato resta prenotato.
 */
final readonly class RulesEditor
{
    public function __construct(
        private ScheduleStore $schedule,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /** Salva le regole. Ogni invio conta per il limite dei tentativi delle modifiche del titolare. */
    public function save(User $staff, RulesChange $rules): RulesOutcome
    {
        $limit = StaffThrottle::hitSchedule($this->limiter, $staff);
        if (!$limit->allowed) {
            return RulesOutcome::throttled($limit->retryAfter);
        }

        $this->schedule->saveRules($rules, $staff->id, $this->clock->now());

        return RulesOutcome::saved();
    }
}
