<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use DateTimeImmutable;
use FourStreet\Account\User;
use FourStreet\Account\UserRepository;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Staff\StaffThrottle;
use FourStreet\Booking\Weekday;
use FourStreet\Clock\Clock;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Text\ItalianDateFormatter;
use PDOException;

/**
 * Le modifiche dell'orario fatte dal titolare: plan() dice come andrebbero adesso (si può? chi è prenotato dove
 * una fascia sparisce?), apply() le fa. Le regole:
 *
 * - il passato non si tocca: né i giorni passati né, oggi, le fasce già cominciate; la settimana tipo cambia da domani;
 * - mai fasce prima dell'inaugurazione, mai due che si sovrappongono (data la durata), mai oltre la mezzanotte, al più
 *   DayTimes::MAX_PER_DAY al giorno, mai un'ora che quel giorno non esiste (il cambio dell'ora);
 * - un giorno con lo stesso orario della settimana tipo torna a seguirla: nessuna eccezione che non serve;
 * - una fascia che sparisce non lascia orfane le sue prenotazioni attive non ancora iniziate: apply() le disdice
 *   (CancelledBy::Admin) nella stessa transazione della modifica, ma solo se sono proprio quelle che il titolare ha
 *   visto e confermato (l'impronta di ChangePlan); poi ai clienti registrati parte l'email della palestra, con il perché.
 *   Un'email che non parte non annulla nulla: l'esito lo dice, e chi non ha l'account lo avvisa il titolare.
 *
 * Due modifiche del titolare insieme (due tocchi in fretta, due telefoni) passano una alla volta: apply() blocca
 * l'orario e solo dopo lo rilegge.
 *
 * La gara con un cliente che prenota nello stesso istante: chi prenota legge l'orario e poi scrive la prenotazione;
 * se la modifica del titolare cade proprio in mezzo (la durata di una transazione, millisecondi), la prenotazione resta
 * attiva in una fascia che non c'è più. Non sparisce di nascosto: il giorno del pannello la mostra "Fuori dall'orario"
 * (si disdice da lì) e il calendario del mese segna quel giorno. Chiudere del tutto la gara vorrebbe dire bloccare
 * l'orario a ogni prenotazione di ogni cliente, per un caso che il titolare vede comunque. Al contrario non succede mai:
 * si disdicono solo le prenotazioni che il titolare ha visto nella conferma.
 */
final readonly class ScheduleEditor
{
    /** Quante volte si rifà una modifica che ha perso una gara con un'altra transazione. */
    private const int ATTEMPTS = 3;

    /** Dopo tanti invii falliti di fila, le altre email non si provano: il server di posta non risponde. */
    private const int FAILURES_BEFORE_GIVING_UP = 2;

    /** Stallo fra due transazioni (MySQL). */
    private const string SERIALIZATION_FAILURE = '40001';

    /** MariaDB 11.6.2+ con innodb_snapshot_isolation: "la riga è cambiata dopo la tua lettura". */
    private const int RECORD_CHANGED_SINCE_READ = 1020;

    /**
     * @param BookingCalendar $calendar il calendario sulla stessa fonte dell'orario ($schedule)
     */
    public function __construct(
        private BookingCalendar $calendar,
        private ScheduleStore $schedule,
        private BookingRepository $bookings,
        private UserRepository $users,
        private BookingNotifier $notifier,
        private RateLimiter $limiter,
        private ItalianDateFormatter $dates,
        private Clock $clock,
    ) {}

    /** Come andrebbe la modifica adesso, senza farla: l'orario si rilegge da capo. */
    public function plan(ScheduleChange $change): ChangePlan
    {
        $this->schedule->forget();
        $now = $this->clock->now();

        return $change->isWeekly() ? $this->planWeekday($change, $now) : $this->planDays($change, $now);
    }

    /**
     * Fa la modifica, se si può. Ogni invio conta per il limite dei tentativi del titolare, prima di ogni controllo.
     * Se toglie fasce con delle prenotazioni, le fa solo con $confirmed uguale all'impronta delle prenotazioni da
     * disdire (ChangePlan::fingerprint(), dalla pagina di conferma); altrimenti NeedsConfirmation, con chi c'è adesso.
     *
     * Le modifiche dell'orario passano una alla volta: la transazione comincia bloccando l'orario (ScheduleStore::lock()),
     * e solo dopo rilegge l'orario e rifà il piano. Così una modifica vale sull'orario lasciato da quella di prima
     * ("togli le 07:00" non rimette le 09:30 che un altro invio ha appena tolto) e disdice le prenotazioni di adesso.
     * Una gara persa con un'altra transazione (uno stallo su MySQL, l'orario portato nel database nello stesso istante)
     * annulla tutto e si rifà da capo.
     *
     * @param string $confirmed le prenotazioni che il titolare ha visto e confermato; vuoto al primo invio
     */
    public function apply(User $staff, ScheduleChange $change, string $confirmed = ''): ChangeOutcome
    {
        $limit = StaffThrottle::hitSchedule($this->limiter, $staff);
        if (!$limit->allowed) {
            return ChangeOutcome::throttled($limit->retryAfter);
        }

        $result = $this->inTransaction($staff, $change, $confirmed);
        if ($result instanceof ChangeOutcome) {
            return $result;
        }

        // Le email dopo la transazione: mai un blocco del database tenuto aperto ad aspettare il server di posta.
        [$plan, $cancelled] = $result;

        return ChangeOutcome::applied($plan, $this->notifyAll($cancelled, $plan));
    }

    /**
     * applyLocked() in una transazione, rifatta da capo se perde una gara con un'altra (al più ATTEMPTS volte).
     *
     * @return array{ChangePlan, list<RosterEntry>}|ChangeOutcome
     */
    private function inTransaction(User $staff, ScheduleChange $change, string $confirmed): array|ChangeOutcome
    {
        for ($attempt = 1; ; ++$attempt) {
            try {
                return $this->schedule->transaction(fn(): array|ChangeOutcome => $this->applyLocked($staff, $change, $confirmed));
            } catch (ScheduleConflict|PDOException $error) {
                if ($attempt >= self::ATTEMPTS || ($error instanceof PDOException && !self::isConflict($error))) {
                    throw $error;
                }
            }
        }
    }

    /**
     * Dentro la transazione, con l'orario bloccato: il piano rifatto sull'orario di adesso, poi la modifica e le disdette.
     * Un esito che non cambia nulla (rifiutata, già così, da confermare) esce subito, senza scrivere.
     *
     * @return array{ChangePlan, list<RosterEntry>}|ChangeOutcome la modifica fatta, con le prenotazioni disdette
     */
    private function applyLocked(User $staff, ScheduleChange $change, string $confirmed): array|ChangeOutcome
    {
        $this->schedule->lock();
        $plan = $this->plan($change);
        if ($plan->isRefused()) {
            return ChangeOutcome::refused($plan);
        }
        if ($plan->changesNothing()) {
            return ChangeOutcome::unchanged($plan);
        }
        // Solo le prenotazioni che il titolare ha visto: se nel frattempo qualcuno ha prenotato dove la fascia sparisce,
        // le rivede e conferma di nuovo.
        if ($plan->affected !== [] && $plan->fingerprint() !== $confirmed) {
            return ChangeOutcome::needsConfirmation($plan);
        }

        $now = $this->clock->now();
        $this->write($plan, $staff, $now);
        $done = [];
        foreach ($plan->affected as $entry) {
            // Già disdetta nel frattempo (dal cliente): non è più da disdire, né da avvisare.
            if ($this->bookings->cancel($entry->booking->id, $now, CancelledBy::Admin)) {
                $done[] = $entry;
            }
        }

        return [$plan, $done];
    }

    /**
     * L'email della palestra a ogni cliente registrato, con il perché. Dopo due invii falliti di fila il server di posta
     * non risponde: gli altri non si provano (ognuno lo aspetterebbe fino al suo limite) e risultano non partiti,
     * così l'avviso dice al titolare chi avvisare.
     *
     * @param list<RosterEntry> $cancelled
     *
     * @return list<CancelledBooking>
     */
    private function notifyAll(array $cancelled, ChangePlan $plan): array
    {
        $results = [];
        $failures = 0;
        foreach ($cancelled as $entry) {
            $email = $failures >= self::FAILURES_BEFORE_GIVING_UP && !$entry->booking->isGuest()
                ? EmailDelivery::Failed
                : $this->notify($entry, $plan);
            $failures = $email === EmailDelivery::Failed ? $failures + 1 : ($email === EmailDelivery::Sent ? 0 : $failures);
            $results[] = new CancelledBooking($entry, $email);
        }

        return $results;
    }

    private function planWeekday(ScheduleChange $change, DateTimeImmutable $now): ChangePlan
    {
        $day = $change->weekday();
        $time = $change->time();
        $before = $this->schedule->week()->on($day);
        $present = self::contains($before, $time);

        if ($change->kind === ChangeKind::AddWeekdayTime) {
            if ($present) {
                return ChangePlan::refused($change, ScheduleRefusal::time(sprintf('%s c\'è già la fascia delle %s.', ucfirst(self::weekdayName($day)), $time)));
            }
            $after = DayTimes::sorted([...$before, $time]);
        } else {
            if (!$present) {
                return ChangePlan::refused($change, ScheduleRefusal::time(sprintf('%s non c\'è la fascia delle %s.', ucfirst(self::weekdayName($day)), $time)));
            }
            $after = DayTimes::without($before, [$time]);
        }
        $problem = DayTimes::problem($after, $this->calendar->rules()->slotMinutes);
        if ($problem !== null) {
            return ChangePlan::refused($change, ScheduleRefusal::time($problem));
        }

        $write = new WeekdayWrite($day, $before, $after, $this->calendar->today($now)->addDays(1));

        return new ChangePlan($change, weekday: $write, affected: $this->affectedByWeekday($write, $now));
    }

    private function planDays(ScheduleChange $change, DateTimeImmutable $now): ChangePlan
    {
        $today = $this->calendar->today($now);
        // Prima il giorno, poi l'orario: un giorno passato è "passato" anche se la fascia c'era già (una pagina rimasta
        // aperta da ieri), non "c'è già".
        if (in_array($change->kind, [ChangeKind::AddTime, ChangeKind::RemoveTime, ChangeKind::BackToWeek], true)) {
            $refusal = $this->outOfReach($change->from(), $today, $now);
            if ($refusal !== null) {
                return ChangePlan::refused($change, $refusal);
            }
        }
        $targets = $this->targets($change, $today);
        if ($targets instanceof ScheduleRefusal) {
            return ChangePlan::refused($change, $targets);
        }

        $writes = [];
        foreach ($targets as [$date, $kind, $times, $note]) {
            $write = $this->resolve($date, $kind, $times, $note, $today, $now);
            if ($write instanceof ScheduleRefusal) {
                return ChangePlan::refused($change, $write);
            }
            if ($write !== null) {
                $writes[] = $write;
            }
        }

        return new ChangePlan($change, days: $writes, affected: $this->affected(new ChangePlan($change, days: $writes), $now));
    }

    /**
     * Che cosa chiede la modifica, giorno per giorno: tornare alla settimana tipo (Week), quegli orari (Changed),
     * chiuso (Closed). Oppure perché non ha senso già così (un orario che c'è già, una settimana già cominciata).
     *
     * @return list<array{LocalDate, DayKind, list<SlotTime>, string|null}>|ScheduleRefusal
     */
    private function targets(ScheduleChange $change, LocalDate $today): array|ScheduleRefusal
    {
        switch ($change->kind) {
            case ChangeKind::AddTime:
            case ChangeKind::RemoveTime:
                $date = $change->from();
                $time = $change->time();
                $day = $this->schedule->day($date);
                if ($change->kind === ChangeKind::AddTime) {
                    if ($day->isClosed()) {
                        return ScheduleRefusal::day('Questo giorno è chiuso: per aggiungere una fascia, prima riaprilo.');
                    }
                    if ($day->has($time)) {
                        return ScheduleRefusal::time(sprintf('La fascia delle %s c\'è già.', $time));
                    }

                    return [[$date, DayKind::Changed, [...$day->times, $time], null]];
                }
                if (!$day->has($time)) {
                    return ScheduleRefusal::time(sprintf('La fascia delle %s non c\'è.', $time));
                }

                return [[$date, DayKind::Changed, DayTimes::without($day->times, [$time]), null]];

            case ChangeKind::Close:
                return array_map(static fn(LocalDate $date): array => [$date, DayKind::Closed, [], $change->note], $change->days());

            case ChangeKind::Reopen:
                $targets = [];
                foreach ($change->days() as $date) {
                    if ($this->schedule->day($date)->isClosed()) {
                        $targets[] = [$date, DayKind::Week, [], null];
                    }
                }

                return $targets === [] ? ScheduleRefusal::day('In questi giorni non c\'è nessuna chiusura da togliere.') : $targets;

            case ChangeKind::BackToWeek:
                $date = $change->from();

                return $this->schedule->day($date)->isException() ? [[$date, DayKind::Week, [], null]] : [];

            case ChangeKind::CopyWeek:
                $next = $change->from()->addDays(7);
                if (!$next->isAfter($today)) {
                    return ScheduleRefusal::day('La settimana dopo è già cominciata: si copia solo in una settimana che deve ancora cominciare.');
                }
                $targets = [];
                foreach ($change->days() as $index => $source) {
                    $day = $this->schedule->day($source);
                    $targets[] = $day->isClosed()
                        ? [$next->addDays($index), DayKind::Closed, [], $day->note]
                        : [$next->addDays($index), DayKind::Changed, $day->times, null];
                }

                return $targets;

            default:
                return ScheduleRefusal::day('Questa modifica non riguarda un giorno preciso.');
        }
    }

    /**
     * Come diventa il giorno, con le regole che valgono per tutti: null se resta com'è, oppure il perché no.
     *
     * @param list<SlotTime> $times gli orari chiesti (solo per Changed)
     */
    private function resolve(LocalDate $date, DayKind $kind, array $times, ?string $note, LocalDate $today, DateTimeImmutable $now): DayWrite|ScheduleRefusal|null
    {
        $refusal = $this->outOfReach($date, $today, $now);
        if ($refusal !== null) {
            return $refusal;
        }

        $current = $this->schedule->day($date);
        $before = $current->times;
        $week = $this->schedule->weekTimesOn($date);
        // Oggi, le fasce già cominciate restano come sono.
        $started = $date->equals($today) ? $this->started($date, $before, $now) : [];
        // Gli orari scritti dal titolare devono poter cominciare; quelli della settimana tipo no: un orario che quel giorno
        // non esiste (prima dell'inaugurazione, l'ora saltata dal cambio dell'ora) lo scarta il calendario, come sempre.
        $requested = $kind === DayKind::Changed;

        if ($kind === DayKind::Closed) {
            if ($started !== []) {
                return ScheduleRefusal::day(sprintf('Oggi la fascia delle %s è già cominciata: oggi non si chiude più. Chiudi da domani, oppure togli una per una le fasce di oggi che restano.', $started[0]));
            }
            $after = [];
        } else {
            if ($kind === DayKind::Changed) {
                $after = DayTimes::sorted($times);
                foreach (DayTimes::without($before, $after) as $time) {
                    if ($this->isPast($date, $time, $now)) {
                        return ScheduleRefusal::time(sprintf('La fascia delle %s di oggi è già cominciata: non si toglie più.', $time));
                    }
                }
                foreach (DayTimes::without($after, $before) as $time) {
                    if ($this->isPast($date, $time, $now)) {
                        return ScheduleRefusal::time(sprintf('Oggi le %s sono già passate: si aggiungono solo fasce che devono ancora cominciare.', $time));
                    }
                }
            } else {
                // Tornare alla settimana tipo, oggi: le fasce cominciate restano, quelle che devono ancora cominciare la seguono.
                $after = $date->equals($today)
                    ? DayTimes::sorted([...$started, ...array_filter($week, fn(SlotTime $time): bool => !$this->isPast($date, $time, $now))])
                    : $week;
            }
            $kind = DayTimes::same($after, $week) ? DayKind::Week : DayKind::Changed;
            $note = null;
        }

        foreach ($requested ? DayTimes::without($after, $before) : [] as $time) {
            $refusal = $this->cannotStart($date, $time);
            if ($refusal !== null) {
                return $refusal;
            }
        }
        $problem = DayTimes::problem($after, $this->calendar->rules()->slotMinutes);
        if ($problem !== null) {
            return ScheduleRefusal::time($problem);
        }

        if ($kind === $current->kind && DayTimes::same($after, $before) && $note === $current->note) {
            return null;
        }

        return new DayWrite($date, $kind, $before, $after, $note);
    }

    /** Il giorno non si cambia: è passato, è prima dell'inaugurazione, o è oltre la portata del pannello. */
    private function outOfReach(LocalDate $date, LocalDate $today, DateTimeImmutable $now): ?ScheduleRefusal
    {
        $openingDay = $this->calendar->openingDay();

        return match (true) {
            $date->isBefore($today) => ScheduleRefusal::day(sprintf('%s è passato: un giorno passato non si cambia.', ucfirst($this->day($date)))),
            $date->isBefore($openingDay) => ScheduleRefusal::day(sprintf('%s è prima dell\'inaugurazione (%s): quel giorno non ci sono fasce.', ucfirst($this->day($date)), $this->day($openingDay))),
            !$this->calendar->isWithinReach($date, $now) => ScheduleRefusal::day('Quel giorno è troppo lontano: il pannello arriva a dieci anni da oggi.'),
            default => null,
        };
    }

    /** Una fascia nuova non può cominciare in un'ora che quel giorno non esiste, né prima dell'inaugurazione. */
    private function cannotStart(LocalDate $date, SlotTime $time): ?ScheduleRefusal
    {
        $start = $this->calendar->startOf($date, $time);
        if ($start === null) {
            return ScheduleRefusal::time(sprintf('%s le %s non esistono: è il giorno del cambio dell\'ora.', ucfirst($this->day($date)), $time));
        }
        $opening = $this->calendar->opening();
        if ($start < $opening) {
            return ScheduleRefusal::time(sprintf('Le fasce cominciano dall\'inaugurazione, %s alle %s.', $this->day($this->calendar->openingDay()), $this->calendar->local($opening)->format('H:i')));
        }

        return null;
    }

    /**
     * Le prenotazioni attive non ancora iniziate che la modifica toglie, per giorno, fascia e posto: una lettura sola.
     *
     * @return list<RosterEntry>
     */
    private function affected(ChangePlan $plan, DateTimeImmutable $now): array
    {
        if ($plan->weekday !== null) {
            return $this->affectedByWeekday($plan->weekday, $now);
        }
        if ($plan->days === []) {
            return [];
        }

        $writes = [];
        $first = $plan->days[0]->date;
        $last = $first;
        foreach ($plan->days as $day) {
            $writes[$day->date->value] = $day;
            $first = $day->date->isBefore($first) ? $day->date : $first;
            $last = $day->date->isAfter($last) ? $day->date : $last;
        }

        $affected = [];
        foreach ($this->bookings->activeEntries($first, $last) as $entry) {
            $write = $writes[$entry->booking->date->value] ?? null;
            if ($write !== null && $entry->booking->startsAt > $now && $write->removes($entry->booking->time)) {
                $affected[] = $entry;
            }
        }

        return $affected;
    }

    /**
     * Le prenotazioni attive dei giorni che seguono la settimana tipo, da quando vale la modifica, dove la fascia sparisce.
     * Un giorno con un orario suo non segue la settimana tipo: la modifica non lo tocca.
     *
     * @return list<RosterEntry>
     */
    private function affectedByWeekday(WeekdayWrite $write, DateTimeImmutable $now): array
    {
        $affected = [];
        foreach ($this->bookings->activeEntries($write->from) as $entry) {
            $booking = $entry->booking;
            if ($booking->date->weekday() === $write->day
                && $booking->startsAt > $now
                && $write->removes($booking->time)
                && !$this->schedule->day($booking->date)->isException()) {
                $affected[] = $entry;
            }
        }

        return $affected;
    }

    private function write(ChangePlan $plan, User $staff, DateTimeImmutable $now): void
    {
        if ($plan->weekday !== null) {
            $this->schedule->saveWeekday($plan->weekday->day, $plan->weekday->after, $plan->weekday->from, $staff->id, $now);
        }
        foreach ($plan->days as $day) {
            if ($day->kind === DayKind::Week) {
                $this->schedule->resetDay($day->date, $staff->id, $now);
            } else {
                $this->schedule->saveDay($day->date, $day->kind === DayKind::Closed, $day->after, $day->note, $staff->id, $now);
            }
        }
    }

    /** L'email della palestra al cliente registrato, con il perché; chi non ha l'account lo avvisa il titolare. */
    private function notify(RosterEntry $entry, ChangePlan $plan): EmailDelivery
    {
        $booking = $entry->booking;
        $customer = $booking->userId === null ? null : $this->users->findById($booking->userId);
        if ($customer === null) {
            return EmailDelivery::NoAddress;
        }

        return $this->notifier->cancelledByGym($customer, $booking, $plan->closes($entry)
            ? 'Quel giorno la palestra resta chiusa.'
            : sprintf('Quel giorno la fascia delle %s non c\'è più.', $booking->time));
    }

    /**
     * Gli orari di quel giorno che sono già cominciati (in corso o finiti).
     *
     * @param list<SlotTime> $times
     *
     * @return list<SlotTime>
     */
    private function started(LocalDate $date, array $times, DateTimeImmutable $now): array
    {
        return array_values(array_filter($times, fn(SlotTime $time): bool => $this->isPast($date, $time, $now)));
    }

    /** Quell'ora di quel giorno è già arrivata: una fascia lì sarebbe già cominciata. */
    private function isPast(LocalDate $date, SlotTime $time, DateTimeImmutable $now): bool
    {
        $start = $this->calendar->startOf($date, $time);

        return $start !== null && $start <= $now;
    }

    /** "lunedì 7 dicembre" (con l'anno, se non è quello di oggi). */
    private function day(LocalDate $date): string
    {
        $year = $this->calendar->today($this->clock->now())->calendarDay()->format('Y');

        return $this->dates->format($date->calendarDay(), withYear: $date->calendarDay()->format('Y') !== $year, capitalize: false);
    }

    /** Una gara persa con un'altra transazione: la stessa che PdoBookingRepository riprova da capo. */
    private static function isConflict(PDOException $error): bool
    {
        $info = $error->errorInfo ?? [];

        return ($info[0] ?? null) === self::SERIALIZATION_FAILURE || ($info[1] ?? null) === self::RECORD_CHANGED_SINCE_READ;
    }

    /** "il lunedì", "la domenica". */
    private static function weekdayName(Weekday $day): string
    {
        return ($day === Weekday::Sunday ? 'la ' : 'il ') . $day->label();
    }

    /**
     * @param list<SlotTime> $times
     */
    private static function contains(array $times, SlotTime $time): bool
    {
        foreach ($times as $existing) {
            if ($existing->equals($time)) {
                return true;
            }
        }

        return false;
    }
}
