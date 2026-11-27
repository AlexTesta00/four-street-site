<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\User;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\BookingStatus;
use FourStreet\Booking\CancelledBy;
use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\FirstFreeSeat;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Occupancy;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\SeatChooser;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Booking\WeeklySchedule;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\SiteConfig;
use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Tests\Support\MovableClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestDatabase;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Le prenotazioni sul database dei test: SQLite in memoria, oppure MariaDB in CI (gruppo "database").
 * La capienza, "una al giorno" e "al massimo N future" valgono nel database, non in un conteggio a parte.
 */
#[Group('database')]
#[CoversClass(PdoBookingRepository::class)]
#[CoversClass(Booking::class)]
#[CoversClass(BookingResult::class)]
#[CoversClass(Occupancy::class)]
#[CoversClass(FirstFreeSeat::class)]
#[UsesClass(BookingCalendar::class)]
#[UsesClass(BookingRules::class)]
#[UsesClass(WeeklySchedule::class)]
#[UsesClass(ExampleSlotCatalog::class)]
#[UsesClass(ScheduledSlot::class)]
#[UsesClass(LocalDate::class)]
#[UsesClass(SlotTime::class)]
#[UsesClass(Weekday::class)]
#[UsesClass(Slot::class)]
#[UsesClass(PdoUserRepository::class)]
#[UsesClass(User::class)]
#[UsesClass(NewUser::class)]
#[UsesClass(EmailAddress::class)]
#[UsesClass(FullName::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Row::class)]
#[UsesClass(UtcDateTime::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(SiteConfig::class)]
final class PdoBookingRepositoryTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private PdoBookingRepository $bookings;

    private BookingCalendar $calendar;

    protected function setUp(): void
    {
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->bookings = new PdoBookingRepository($this->database);
        $this->calendar = BookingCalendar::forSite(SiteFixture::config());
    }

    public function testEachBookingTakesTheFirstFreeSeatUntilTheSlotIsFull(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');

        $first = $this->bookings->place($this->customer('a@example.com')->id, $slot, 5, null, $this->clock->now());
        self::assertSame(BookingStatus::Booked, $first->status);
        $booking = $first->booking();
        self::assertSame('2026-12-07', $booking->date->value);
        self::assertSame('18:30', $booking->time->value);
        self::assertSame('2026-12-07 17:30:00', UtcDateTime::format($booking->startsAt), 'L\'inizio si scrive in UTC.');
        self::assertSame(1, $booking->seat());
        self::assertSame('2026-11-25 07:00:00', UtcDateTime::format($booking->createdAt));
        self::assertTrue($booking->isActive());
        self::assertNull($booking->cancelledBy);
        self::assertSame($slot, $first->slot());

        foreach (['b', 'c', 'd', 'e'] as $index => $name) {
            self::assertSame($index + 2, $this->bookings->place($this->customer($name . '@example.com')->id, $slot, 5, null, $this->clock->now())->booking()->seat());
        }

        $full = $this->bookings->place($this->customer('f@example.com')->id, $slot, 5, null, $this->clock->now());
        self::assertSame(BookingStatus::Full, $full->status);
        self::assertSame([1, 2, 3, 4, 5], $this->bookings->occupancy($slot->date, $slot->date)->seatsOf($slot->date, $slot->time));
    }

    public function testOneActiveBookingPerCustomerPerDay(): void
    {
        $customer = $this->customer('a@example.com');
        $evening = $this->bookings->place($customer->id, $this->slot('2026-12-07', '18:30'), 5, null, $this->clock->now())->booking();

        $morning = $this->bookings->place($customer->id, $this->slot('2026-12-07', '07:00'), 5, null, $this->clock->now());
        self::assertSame(BookingStatus::AlreadyBooked, $morning->status);
        self::assertSame($evening->id, $morning->booking()->id);
        self::assertFalse($morning->isRepeat());
        self::assertTrue($this->bookings->place($customer->id, $this->slot('2026-12-07', '18:30'), 5, null, $this->clock->now())->isRepeat());
        self::assertSame($evening->id, $this->bookings->activeOn($customer->id, self::day('2026-12-07'))?->id);

        // Un altro giorno sì; e disdetta quella di lunedì, lunedì si riprenota.
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-08', '07:00'), 5, null, $this->clock->now())->status);
        self::assertTrue($this->bookings->cancel($evening->id, $this->clock->now(), CancelledBy::Customer));
        self::assertNull($this->bookings->activeOn($customer->id, self::day('2026-12-07')));
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-07', '07:00'), 5, null, $this->clock->now())->status);
    }

    public function testAtMostNUpcomingBookingsAndOnlyTheOnesThatHaveNotStarted(): void
    {
        $customer = $this->customer('a@example.com');
        $now = $this->clock->now();
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-07', '07:00'), 5, 2, $now)->status);
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-08', '07:00'), 5, 2, $now)->status);

        $third = $this->bookings->place($customer->id, $this->slot('2026-12-09', '07:00'), 5, 2, $now);
        self::assertSame(BookingStatus::TooManyUpcoming, $third->status);
        self::assertSame(2, $this->bookings->countUpcoming($customer->id, $now));

        // Iniziata la prima, ne resta una futura: la terza passa. Senza limite passa sempre.
        $later = new DateTimeImmutable('2026-12-07 07:00:00', new DateTimeZone('Europe/Rome'));
        self::assertSame(1, $this->bookings->countUpcoming($customer->id, $later));
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-09', '07:00'), 5, 2, $later)->status);
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-10', '07:00'), 5, null, $later)->status);
    }

    public function testACancelledBookingFreesItsSeatAndStaysAsHistory(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $ids = [];
        foreach (['a', 'b', 'c'] as $name) {
            $ids[] = $this->bookings->place($this->customer($name . '@example.com')->id, $slot, 5, null, $this->clock->now())->booking()->id;
        }
        $this->clock->travel('+1 hour');

        self::assertTrue($this->bookings->cancel($ids[1], $this->clock->now(), CancelledBy::Admin));
        self::assertFalse($this->bookings->cancel($ids[1], $this->clock->now(), CancelledBy::Admin), 'Una volta sola.');
        self::assertFalse($this->bookings->cancel(PHP_INT_MAX, $this->clock->now(), CancelledBy::Customer));

        $cancelled = $this->bookings->find($ids[1]);
        self::assertNotNull($cancelled);
        self::assertFalse($cancelled->isActive());
        self::assertNull($cancelled->seat);
        self::assertSame(CancelledBy::Admin, $cancelled->cancelledBy);
        self::assertSame('2026-11-25 08:00:00', UtcDateTime::format($cancelled->cancelledAt ?? self::fail()));
        self::assertSame([1, 3], $this->bookings->occupancy($slot->date, $slot->date)->seatsOf($slot->date, $slot->time));

        // Il posto 2 è di nuovo libero: il prossimo lo prende.
        self::assertSame(2, $this->bookings->place($this->customer('d@example.com')->id, $slot, 5, null, $this->clock->now())->booking()->seat());
        self::assertNull($this->bookings->find(PHP_INT_MAX));
    }

    public function testTheBookingsOfACustomerFromADay(): void
    {
        $anna = $this->customer('anna@example.com');
        $luca = $this->customer('luca@example.com');
        $now = $this->clock->now();
        $wednesday = $this->bookings->place($anna->id, $this->slot('2026-12-09', '07:00'), 5, null, $now)->booking();
        $monday = $this->bookings->place($anna->id, $this->slot('2026-12-07', '20:00'), 5, null, $now)->booking();
        $tuesday = $this->bookings->place($anna->id, $this->slot('2026-12-08', '12:30'), 5, null, $now)->booking();
        $this->bookings->place($luca->id, $this->slot('2026-12-08', '12:30'), 5, null, $now);
        $this->bookings->cancel($tuesday->id, $now, CancelledBy::Customer);

        self::assertSame([$monday->id, $wednesday->id], array_map(static fn(Booking $booking): int => $booking->id, $this->bookings->activeFrom($anna->id, self::day('2026-12-07'))));
        self::assertSame([$wednesday->id], array_map(static fn(Booking $booking): int => $booking->id, $this->bookings->activeFrom($anna->id, self::day('2026-12-08'))));
        self::assertSame(2, $this->bookings->countUpcoming($anna->id, $now));
        self::assertTrue($monday->belongsTo($anna->id));
        self::assertFalse($monday->belongsTo($luca->id));
    }

    public function testTheOccupancyOfSomeDays(): void
    {
        $now = $this->clock->now();
        $this->bookings->place($this->customer('a@example.com')->id, $this->slot('2026-12-07', '07:00'), 5, null, $now);
        $this->bookings->place($this->customer('b@example.com')->id, $this->slot('2026-12-07', '07:00'), 5, null, $now);
        $this->bookings->place($this->customer('c@example.com')->id, $this->slot('2026-12-09', '18:30'), 5, null, $now);

        $occupancy = $this->bookings->occupancy(self::day('2026-12-07'), self::day('2026-12-08'));

        self::assertSame([1, 2], $occupancy->seatsOf(self::day('2026-12-07'), self::time('07:00')));
        self::assertSame([], $occupancy->seatsOf(self::day('2026-12-09'), self::time('18:30')), 'Fuori dai giorni letti.');
    }

    public function testWhenAnotherBookingTakesTheSeatFirstItTriesTheNextOne(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $this->bookings->place($this->customer('a@example.com')->id, $slot, 5, null, $this->clock->now());

        // La lettura dei posti è vecchia di un istante: il posto 1 sembra libero, ma l'ha appena preso un altro.
        $stale = new class implements SeatChooser {
            public int $calls = 0;

            public function choose(array $taken, int $capacity): ?int
            {
                ++$this->calls;

                return $this->calls === 1 ? 1 : FirstFreeSeat::among($taken, $capacity);
            }
        };
        $result = (new PdoBookingRepository($this->database, $stale))->place($this->customer('b@example.com')->id, $slot, 5, null, $this->clock->now());

        self::assertSame(BookingStatus::Booked, $result->status);
        self::assertSame(2, $result->booking()->seat());
        self::assertSame(2, $stale->calls, 'Il vincolo del database ha respinto il posto 1: un secondo tentativo, con una lettura nuova.');
        self::assertSame([1, 2], $this->bookings->occupancy($slot->date, $slot->date)->seatsOf($slot->date, $slot->time));
    }

    public function testItGivesUpLoudlyIfTheSeatKeepsBeingTaken(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $this->bookings->place($this->customer('a@example.com')->id, $slot, 5, null, $this->clock->now());
        $alwaysOne = new class implements SeatChooser {
            public function choose(array $taken, int $capacity): int
            {
                return 1;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nessun posto assegnato nella fascia 2026-12-07 18:30 dopo 7 tentativi.');

        (new PdoBookingRepository($this->database, $alwaysOne))->place($this->customer('b@example.com')->id, $slot, 5, null, $this->clock->now());
    }

    public function testTheDatabaseItselfKeepsOneBookingPerSeatAndOneActivePerDay(): void
    {
        $anna = $this->customer('anna@example.com');
        $luca = $this->customer('luca@example.com');
        $row = ['slot_date' => '2026-12-07', 'slot_time' => '18:30', 'starts_at' => '2026-12-07 17:30:00', 'created_at' => '2026-11-25 07:00:00'];
        $this->insert([...$row, 'user_id' => $anna->id, 'seat' => 1, 'active' => 1]);

        foreach ([
            'stesso posto, altro cliente' => [...$row, 'user_id' => $luca->id, 'seat' => 1, 'active' => 1],
            'stesso giorno, altro posto' => [...$row, 'slot_time' => '07:00', 'starts_at' => '2026-12-07 06:00:00', 'user_id' => $anna->id, 'seat' => 2, 'active' => 1],
        ] as $case => $duplicate) {
            try {
                $this->insert($duplicate);
                self::fail('Il database doveva rifiutare: ' . $case);
            } catch (PDOException) {
                // Rifiutata, come deve.
            }
        }

        // Le disdette non occupano né posto né giorno: ce ne possono essere quante si vuole.
        foreach ([1, 2] as $ignored) {
            $this->insert([...$row, 'user_id' => $anna->id, 'seat' => null, 'active' => null, 'cancelled_at' => '2026-11-25 07:30:00', 'cancelled_by' => 'customer']);
        }
        self::assertSame(3, $this->database->fetchOne('SELECT COUNT(*) AS total FROM bookings')?->int('total'));
    }

    private function slot(string $date, string $time): ScheduledSlot
    {
        return $this->calendar->slot(self::day($date), self::time($time))
            ?? self::fail(sprintf('Nessuna fascia il %s alle %s.', $date, $time));
    }

    private static function day(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail(sprintf('Giorno non valido: %s.', $value));
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? self::fail(sprintf('Orario non valido: %s.', $value));
    }

    private function customer(string $email): User
    {
        $users = new PdoUserRepository($this->database);

        return $users->create(new NewUser(
            FullName::tryFrom('Cliente Prova') ?? self::fail(),
            EmailAddress::tryFrom($email) ?? self::fail(),
            'hash',
            $this->clock->now(),
            str_repeat('0', 64),
        ));
    }

    /**
     * @param array<string, int|string|null> $row
     */
    private function insert(array $row): void
    {
        $columns = array_keys($row);
        $this->database->execute(
            sprintf('INSERT INTO bookings (%s) VALUES (%s)', implode(', ', $columns), implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns))),
            $row,
        );
    }
}
