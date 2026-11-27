<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\DayKind;
use FourStreet\Booking\Schedule\DayPlan;
use FourStreet\Booking\Schedule\DayTimes;
use FourStreet\Booking\SlotTime;

/**
 * Un giorno della settimana che il titolare sta cambiando: il suo orario, da dove viene, le persone prenotate per fascia
 * e cosa si può fare adesso. Le stesse regole di Schedule\ScheduleEditor, che comunque ricontrolla tutto.
 */
final readonly class WeekSheetDay
{
    /**
     * @param list<SlotTime> $weekTimes gli orari della settimana tipo per quel giorno: dove torna con "settimana tipo"
     * @param list<DayTime> $times le fasce dell'orario del giorno, dalla prima
     * @param int $outOfSchedule le prenotazioni attive in fasce che l'orario non ha più (si vedono e si disdicono nel giorno)
     */
    public function __construct(
        public LocalDate $date,
        public DayPlan $plan,
        public array $weekTimes,
        public array $times,
        public int $outOfSchedule,
        public bool $isToday,
        public bool $isPast,
        public bool $isBeforeOpening,
    ) {}

    /** Il giorno si cambia: non è passato e non è prima dell'inaugurazione. */
    public function isEditable(): bool
    {
        return !$this->isPast && !$this->isBeforeOpening;
    }

    /** Si aggiunge una fascia: il giorno si cambia, non è chiuso e non ha già il massimo di fasce. */
    public function canAdd(): bool
    {
        return $this->isEditable() && !$this->plan->isClosed() && count($this->times) < DayTimes::MAX_PER_DAY;
    }

    /** Quella fascia si toglie: il giorno si cambia e la fascia non è già cominciata. */
    public function canRemove(DayTime $time): bool
    {
        return $this->isEditable() && !$time->started;
    }

    /** Si chiude: il giorno si cambia, non è già chiuso e (oggi) nessuna fascia è già cominciata. */
    public function canClose(): bool
    {
        if (!$this->isEditable() || $this->plan->isClosed()) {
            return false;
        }
        foreach ($this->times as $time) {
            if ($time->started) {
                return false;
            }
        }

        return true;
    }

    /** Si riapre: è chiuso e si cambia. Riaperto, segue la settimana tipo. */
    public function canReopen(): bool
    {
        return $this->isEditable() && $this->plan->isClosed();
    }

    /** Torna alla settimana tipo: ha un orario suo (non chiuso) e si cambia. */
    public function canGoBackToWeek(): bool
    {
        return $this->isEditable() && $this->plan->kind === DayKind::Changed;
    }

    /** Le prenotazioni attive del giorno, nelle fasce dell'orario e fuori. */
    public function booked(): int
    {
        return array_sum(array_map(static fn(DayTime $time): int => $time->booked, $this->times)) + $this->outOfSchedule;
    }
}
