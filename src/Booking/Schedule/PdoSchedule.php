<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use Closure;
use DateTimeImmutable;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Booking\WeeklySchedule;
use FourStreet\Database\Database;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use PDOException;
use UnexpectedValueException;

/**
 * L'orario e le regole nel database (booking_settings, schedule_weekly_slots, schedule_days, schedule_day_slots;
 * SQLite e MySQL/MariaDB, lo stesso SQL).
 *
 * Si legge poco, e una volta: le impostazioni in una query, la settimana tipo in una, le eccezioni un mese alla volta
 * (la landing e /prenota/ ne toccano uno o due), mai una query per giorno. Ciò che ha letto resta in memoria fino
 * a forget(), che ogni scrittura chiama da sé: un'Application serve una richiesta sola e ne ha una sola
 * (Application::create()), così ogni pagina vede un orario coerente dall'inizio alla fine. Finché il titolare non
 * cambia l'orario (la riga "schedule" fra le impostazioni) basta la prima query: l'orario è quello del file.
 *
 * Valori che il pannello non avrebbe mai scritto sono un errore, come in PdoBookingRepository: mai un orario inventato.
 * Ogni segnaposto compare una volta sola per istruzione: MySQL, senza emulazione, non ne accetta di ripetuti.
 */
final class PdoSchedule implements ScheduleStore
{
    /** La riga delle impostazioni che segna "l'orario sta nel database". */
    private const string SCHEDULE_KEY = 'schedule';
    private const string IN_DATABASE = 'database';

    /** Violazione di un vincolo: qui, un'altra richiesta ha appena portato l'orario nel database. */
    private const string INTEGRITY_VIOLATION = '23000';

    /** @var array<string, string>|null nome => valore */
    private ?array $settings = null;

    private ?BookingRules $rules = null;

    /** @var list<array{weekday: int, time: SlotTime, from: LocalDate|null, until: LocalDate|null}>|null */
    private ?array $weekRows = null;

    /** @var array<string, array<string, DayPlan>> mese ("2026-12") => giorno ("2026-12-24") => il suo orario */
    private array $months = [];

    /**
     * @param BookingRules $file le regole di config/site.php: i valori iniziali, e l'orario finché il titolare non lo cambia
     */
    public function __construct(
        private readonly Database $database,
        private readonly BookingRules $file,
    ) {}

    public function rules(): BookingRules
    {
        if ($this->rules === null) {
            $settings = $this->settings();
            $this->rules = $this->file->with(
                online: self::flag($settings, 'online'),
                daysAhead: self::number($settings, 'days_ahead', 0, BookingRules::MAX_DAYS_AHEAD),
                bookingClosesMinutesBefore: self::number($settings, 'booking_closes_minutes_before', 0, BookingRules::MAX_MINUTES_BEFORE),
                cancelClosesMinutesBefore: self::number($settings, 'cancel_closes_minutes_before', 0, BookingRules::MAX_MINUTES_BEFORE),
                maxUpcoming: self::maxUpcoming($settings),
            );
        }

        return $this->rules;
    }

    public function day(LocalDate $date): DayPlan
    {
        if (!$this->isInDatabase()) {
            return (new FileSchedule($this->file))->day($date);
        }

        return $this->exceptionsIn($date)[$date->value] ?? new DayPlan($date, DayKind::Week, $this->weekTimesOn($date));
    }

    public function week(): WeeklySchedule
    {
        if (!$this->isInDatabase()) {
            return $this->file->schedule;
        }

        $days = [];
        foreach ($this->weekRows() as $row) {
            if ($row['until'] === null) {
                $days[$row['weekday']][] = $row['time'];
            }
        }

        // Due righe aperte con lo stesso orario non dovrebbero esserci; se ci fossero, l'orario resta uno.
        return WeeklySchedule::of(array_map(DayTimes::sorted(...), $days));
    }

    public function isInDatabase(): bool
    {
        return ($this->settings()[self::SCHEDULE_KEY] ?? null) === self::IN_DATABASE;
    }

    public function weekTimesOn(LocalDate $date): array
    {
        if (!$this->isInDatabase()) {
            return $this->file->schedule->on($date->weekday());
        }

        $weekday = $date->weekday()->value;
        $times = [];
        foreach ($this->weekRows() as $row) {
            if ($row['weekday'] === $weekday
                && ($row['from'] === null || !$date->isBefore($row['from']))
                && ($row['until'] === null || $date->isBefore($row['until']))) {
                $times[] = $row['time'];
            }
        }

        return DayTimes::sorted($times);
    }

    public function closuresFrom(LocalDate $from): array
    {
        if (!$this->isInDatabase()) {
            $closures = [];
            foreach ($this->file->closedDates as $date) {
                if (!$date->isBefore($from)) {
                    $closures[$date->value] = new DayPlan($date, DayKind::Closed, []);
                }
            }
            ksort($closures, SORT_STRING);

            return array_values($closures);
        }

        return array_map(
            static fn(Row $row): DayPlan => new DayPlan(self::date($row->string('slot_date')), DayKind::Closed, [], $row->nullableString('note')),
            $this->database->fetchAll('SELECT slot_date, note FROM schedule_days WHERE closed = 1 AND slot_date >= :from_date ORDER BY slot_date', ['from_date' => $from->value]),
        );
    }

    public function lock(): void
    {
        // Una scrittura che non cambia nulla: su MySQL blocca la riga "schedule" (o, prima che ci sia, il punto dove
        // andrebbe) fino alla fine della transazione; su SQLite la scrittura comincia subito e una seconda aspetta.
        $this->database->execute('UPDATE booking_settings SET updated_at = updated_at WHERE name = :name', ['name' => self::SCHEDULE_KEY]);
        $this->forget();
    }

    public function transaction(Closure $work): mixed
    {
        try {
            return $this->database->transaction($work);
        } finally {
            // Ciò che si è letto dentro la transazione (anche annullata) non vale più: dopo, si rilegge.
            $this->forget();
        }
    }

    public function saveWeekday(Weekday $day, array $times, LocalDate $from, ?int $by, DateTimeImmutable $at): void
    {
        $this->transaction(function () use ($day, $times, $from, $by, $at): void {
            $this->moveToDatabase($by, $at);

            $kept = [];
            foreach ($this->database->fetchAll(
                'SELECT id, slot_time, valid_from FROM schedule_weekly_slots WHERE weekday = :weekday AND valid_until IS NULL',
                ['weekday' => $day->value],
            ) as $row) {
                $time = self::time($row->string('slot_time'));
                if (self::contains($times, $time)) {
                    $kept[$time->value] = true;

                    continue;
                }
                $validFrom = $row->nullableString('valid_from');
                // Una riga nata da $from in poi non ha mai valso: si toglie. Le altre valgono fino al giorno prima di $from.
                if ($validFrom !== null && !self::date($validFrom)->isBefore($from)) {
                    $this->database->execute('DELETE FROM schedule_weekly_slots WHERE id = :id', ['id' => $row->int('id')]);
                } else {
                    $this->database->execute('UPDATE schedule_weekly_slots SET valid_until = :valid_until WHERE id = :id', ['valid_until' => $from->value, 'id' => $row->int('id')]);
                }
            }

            foreach ($times as $time) {
                if (!isset($kept[$time->value])) {
                    $this->database->execute(
                        'INSERT INTO schedule_weekly_slots (weekday, slot_time, valid_from, valid_until) VALUES (:weekday, :slot_time, :valid_from, NULL)',
                        ['weekday' => $day->value, 'slot_time' => $time->value, 'valid_from' => $from->value],
                    );
                }
            }
        });
    }

    public function saveDay(LocalDate $date, bool $closed, array $times, ?string $note, ?int $by, DateTimeImmutable $at): void
    {
        $this->transaction(function () use ($date, $closed, $times, $note, $by, $at): void {
            $this->moveToDatabase($by, $at);
            $this->deleteDay($date);
            $this->database->execute(
                'INSERT INTO schedule_days (slot_date, closed, note, updated_by, updated_at) VALUES (:slot_date, :closed, :note, :updated_by, :updated_at)',
                ['slot_date' => $date->value, 'closed' => $closed ? 1 : 0, 'note' => $note, 'updated_by' => $by, 'updated_at' => UtcDateTime::format($at)],
            );
            foreach ($closed ? [] : $times as $time) {
                $this->database->execute(
                    'INSERT INTO schedule_day_slots (slot_date, slot_time) VALUES (:slot_date, :slot_time)',
                    ['slot_date' => $date->value, 'slot_time' => $time->value],
                );
            }
        });
    }

    public function resetDay(LocalDate $date, ?int $by, DateTimeImmutable $at): void
    {
        $this->transaction(function () use ($date, $by, $at): void {
            // Anche un closed_dates del file, per riaprirlo, passa prima nel database.
            $this->moveToDatabase($by, $at);
            $this->deleteDay($date);
        });
    }

    public function saveRules(RulesChange $rules, ?int $by, DateTimeImmutable $at): void
    {
        $this->transaction(function () use ($rules, $by, $at): void {
            foreach ($rules->values() as $name => $value) {
                $this->database->execute('DELETE FROM booking_settings WHERE name = :name', ['name' => $name]);
                $this->insertSetting($name, $value, $by, $at);
            }
        });
    }

    public function forget(): void
    {
        $this->settings = null;
        $this->rules = null;
        $this->weekRows = null;
        $this->months = [];
    }

    /**
     * La prima modifica dell'orario: la riga "schedule" fra le impostazioni, poi la settimana tipo e i closed_dates del file
     * copiati nel database, nella stessa transazione della modifica. La riga va scritta per prima: se due richieste ci
     * provano insieme, il database ne fa passare una (la chiave primaria), e l'altra ricomincia da capo
     * (ScheduleConflict): su MySQL quello che ha già letto non vedrebbe le righe copiate dalla prima.
     */
    private function moveToDatabase(?int $by, DateTimeImmutable $at): void
    {
        $this->forget();
        if ($this->isInDatabase()) {
            return;
        }

        try {
            $this->insertSetting(self::SCHEDULE_KEY, self::IN_DATABASE, $by, $at);
        } catch (PDOException $error) {
            $state = $error->errorInfo[0] ?? null;
            if ($state === self::INTEGRITY_VIOLATION) {
                throw new ScheduleConflict('L\'orario è passato nel database nello stesso istante, con un\'altra richiesta.', 0, $error);
            }

            throw $error;
        }

        foreach (Weekday::cases() as $day) {
            foreach ($this->file->schedule->on($day) as $time) {
                $this->database->execute(
                    'INSERT INTO schedule_weekly_slots (weekday, slot_time, valid_from, valid_until) VALUES (:weekday, :slot_time, NULL, NULL)',
                    ['weekday' => $day->value, 'slot_time' => $time->value],
                );
            }
        }
        $closed = [];
        foreach ($this->file->closedDates as $date) {
            $closed[$date->value] = $date;
        }
        foreach ($closed as $date) {
            $this->database->execute(
                'INSERT INTO schedule_days (slot_date, closed, note, updated_by, updated_at) VALUES (:slot_date, 1, NULL, NULL, :updated_at)',
                ['slot_date' => $date->value, 'updated_at' => UtcDateTime::format($at)],
            );
        }
        $this->forget();
    }

    private function insertSetting(string $name, string $value, ?int $by, DateTimeImmutable $at): void
    {
        $this->database->execute(
            'INSERT INTO booking_settings (name, value, updated_by, updated_at) VALUES (:name, :value, :updated_by, :updated_at)',
            ['name' => $name, 'value' => $value, 'updated_by' => $by, 'updated_at' => UtcDateTime::format($at)],
        );
    }

    private function deleteDay(LocalDate $date): void
    {
        $this->database->execute('DELETE FROM schedule_day_slots WHERE slot_date = :slot_date', ['slot_date' => $date->value]);
        $this->database->execute('DELETE FROM schedule_days WHERE slot_date = :slot_date', ['slot_date' => $date->value]);
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        if ($this->settings === null) {
            $settings = [];
            foreach ($this->database->fetchAll('SELECT name, value FROM booking_settings') as $row) {
                $settings[$row->string('name')] = $row->string('value');
            }
            $this->settings = $settings;
        }

        return $this->settings;
    }

    /**
     * Tutte le righe della settimana tipo, anche quelle che non valgono più (servono ai giorni passati): poche decine.
     *
     * @return list<array{weekday: int, time: SlotTime, from: LocalDate|null, until: LocalDate|null}>
     */
    private function weekRows(): array
    {
        if ($this->weekRows === null) {
            $rows = [];
            foreach ($this->database->fetchAll('SELECT weekday, slot_time, valid_from, valid_until FROM schedule_weekly_slots ORDER BY weekday, slot_time') as $row) {
                $weekday = Weekday::tryFrom($row->int('weekday'))
                    ?? throw new UnexpectedValueException(sprintf('Giorno della settimana non valido nel database: %d.', $row->int('weekday')));
                $from = $row->nullableString('valid_from');
                $until = $row->nullableString('valid_until');
                $rows[] = [
                    'weekday' => $weekday->value,
                    'time' => self::time($row->string('slot_time')),
                    'from' => $from === null ? null : self::date($from),
                    'until' => $until === null ? null : self::date($until),
                ];
            }
            $this->weekRows = $rows;
        }

        return $this->weekRows;
    }

    /**
     * Le eccezioni del mese di quel giorno, lette tutte insieme la prima volta che serve un giorno di quel mese.
     *
     * @return array<string, DayPlan>
     */
    private function exceptionsIn(LocalDate $date): array
    {
        $month = substr($date->value, 0, 7);
        if (isset($this->months[$month])) {
            return $this->months[$month];
        }

        $calendarDay = $date->calendarDay();
        $days = [];
        foreach ($this->database->fetchAll(
            'SELECT d.slot_date AS slot_date, d.closed AS closed, d.note AS note, s.slot_time AS slot_time'
            . ' FROM schedule_days d LEFT JOIN schedule_day_slots s ON s.slot_date = d.slot_date'
            . ' WHERE d.slot_date >= :first_date AND d.slot_date <= :last_date ORDER BY d.slot_date, s.slot_time',
            ['first_date' => $calendarDay->format('Y-m-01'), 'last_date' => $calendarDay->format('Y-m-t')],
        ) as $row) {
            $day = $row->string('slot_date');
            $days[$day] ??= ['closed' => $row->int('closed') === 1, 'note' => $row->nullableString('note'), 'times' => []];
            $time = $row->nullableString('slot_time');
            if ($time !== null) {
                $days[$day]['times'][] = self::time($time);
            }
        }

        $plans = [];
        foreach ($days as $day => $values) {
            $plans[$day] = $values['closed']
                ? new DayPlan(self::date($day), DayKind::Closed, [], $values['note'])
                : new DayPlan(self::date($day), DayKind::Changed, DayTimes::sorted($values['times']), $values['note']);
        }

        return $this->months[$month] = $plans;
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

    /**
     * @param array<string, string> $settings
     */
    private static function flag(array $settings, string $name): ?bool
    {
        return match ($settings[$name] ?? null) {
            null => null,
            '1' => true,
            '0' => false,
            default => throw new UnexpectedValueException(sprintf('Impostazione "%s" non valida nel database.', $name)),
        };
    }

    /**
     * @param array<string, string> $settings
     */
    private static function number(array $settings, string $name, int $min, int $max): ?int
    {
        $value = $settings[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (preg_match('/\A\d{1,6}\z/', $value) !== 1 || (int) $value < $min || (int) $value > $max) {
            throw new UnexpectedValueException(sprintf('Impostazione "%s" non valida nel database.', $name));
        }

        return (int) $value;
    }

    /**
     * @param array<string, string> $settings
     *
     * @return positive-int|null
     */
    private static function maxUpcoming(array $settings): ?int
    {
        $value = self::number($settings, 'max_upcoming', 1, RulesChange::MAX_UPCOMING);

        return $value === null || $value >= 1 ? $value : throw new UnexpectedValueException('Impostazione "max_upcoming" non valida nel database.');
    }

    private static function date(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? throw new UnexpectedValueException(sprintf('Giorno non valido nel database: "%s".', $value));
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? throw new UnexpectedValueException(sprintf('Orario non valido nel database: "%s".', $value));
    }
}
