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
use FourStreet\Booking\CreatedBy;
use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\FirstFreeSeat;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Occupancy;
use FourStreet\Booking\PdoBookingRepository;
use FourStreet\Booking\RosterEntry;
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
#[CoversClass(RosterEntry::class)]
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

        $first = $this->bookings->place($this->customer('a@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);
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
            self::assertSame($index + 2, $this->bookings->place($this->customer($name . '@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer)->booking()->seat());
        }

        $full = $this->bookings->place($this->customer('f@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);
        self::assertSame(BookingStatus::Full, $full->status);
        self::assertSame([1, 2, 3, 4, 5], $this->bookings->occupancy($slot->date, $slot->date)->seatsOf($slot->date, $slot->time));
    }

    public function testOneActiveBookingPerCustomerPerDay(): void
    {
        $customer = $this->customer('a@example.com');
        $evening = $this->bookings->place($customer->id, $this->slot('2026-12-07', '18:30'), 5, null, $this->clock->now(), CreatedBy::Customer)->booking();

        $morning = $this->bookings->place($customer->id, $this->slot('2026-12-07', '07:00'), 5, null, $this->clock->now(), CreatedBy::Customer);
        self::assertSame(BookingStatus::AlreadyBooked, $morning->status);
        self::assertSame($evening->id, $morning->booking()->id);
        self::assertFalse($morning->isRepeat());
        self::assertTrue($this->bookings->place($customer->id, $this->slot('2026-12-07', '18:30'), 5, null, $this->clock->now(), CreatedBy::Customer)->isRepeat());
        self::assertSame($evening->id, $this->bookings->activeOn($customer->id, self::day('2026-12-07'))?->id);

        // Un altro giorno sì; e disdetta quella di lunedì, lunedì si riprenota.
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-08', '07:00'), 5, null, $this->clock->now(), CreatedBy::Customer)->status);
        self::assertTrue($this->bookings->cancel($evening->id, $this->clock->now(), CancelledBy::Customer));
        self::assertNull($this->bookings->activeOn($customer->id, self::day('2026-12-07')));
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-07', '07:00'), 5, null, $this->clock->now(), CreatedBy::Customer)->status);
    }

    public function testAtMostNUpcomingBookingsAndOnlyTheOnesThatHaveNotStarted(): void
    {
        $customer = $this->customer('a@example.com');
        $now = $this->clock->now();
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-07', '07:00'), 5, 2, $now, CreatedBy::Customer)->status);
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-08', '07:00'), 5, 2, $now, CreatedBy::Customer)->status);

        $third = $this->bookings->place($customer->id, $this->slot('2026-12-09', '07:00'), 5, 2, $now, CreatedBy::Customer);
        self::assertSame(BookingStatus::TooManyUpcoming, $third->status);
        self::assertSame(2, $this->bookings->countUpcoming($customer->id, $now));

        // Iniziata la prima, ne resta una futura: la terza passa. Senza limite passa sempre.
        $later = new DateTimeImmutable('2026-12-07 07:00:00', new DateTimeZone('Europe/Rome'));
        self::assertSame(1, $this->bookings->countUpcoming($customer->id, $later));
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-09', '07:00'), 5, 2, $later, CreatedBy::Customer)->status);
        self::assertSame(BookingStatus::Booked, $this->bookings->place($customer->id, $this->slot('2026-12-10', '07:00'), 5, null, $later, CreatedBy::Customer)->status);
    }

    public function testACancelledBookingFreesItsSeatAndStaysAsHistory(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $ids = [];
        foreach (['a', 'b', 'c'] as $name) {
            $ids[] = $this->bookings->place($this->customer($name . '@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer)->booking()->id;
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
        self::assertSame(2, $this->bookings->place($this->customer('d@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer)->booking()->seat());
        self::assertNull($this->bookings->find(PHP_INT_MAX));
    }

    public function testTheBookingsOfACustomerFromADay(): void
    {
        $anna = $this->customer('anna@example.com');
        $luca = $this->customer('luca@example.com');
        $now = $this->clock->now();
        $wednesday = $this->bookings->place($anna->id, $this->slot('2026-12-09', '07:00'), 5, null, $now, CreatedBy::Customer)->booking();
        $monday = $this->bookings->place($anna->id, $this->slot('2026-12-07', '20:00'), 5, null, $now, CreatedBy::Customer)->booking();
        $tuesday = $this->bookings->place($anna->id, $this->slot('2026-12-08', '12:30'), 5, null, $now, CreatedBy::Customer)->booking();
        $this->bookings->place($luca->id, $this->slot('2026-12-08', '12:30'), 5, null, $now, CreatedBy::Customer);
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
        $this->bookings->place($this->customer('a@example.com')->id, $this->slot('2026-12-07', '07:00'), 5, null, $now, CreatedBy::Customer);
        $this->bookings->place($this->customer('b@example.com')->id, $this->slot('2026-12-07', '07:00'), 5, null, $now, CreatedBy::Customer);
        $this->bookings->place($this->customer('c@example.com')->id, $this->slot('2026-12-09', '18:30'), 5, null, $now, CreatedBy::Customer);

        $occupancy = $this->bookings->occupancy(self::day('2026-12-07'), self::day('2026-12-08'));

        self::assertSame([1, 2], $occupancy->seatsOf(self::day('2026-12-07'), self::time('07:00')));
        self::assertSame([], $occupancy->seatsOf(self::day('2026-12-09'), self::time('18:30')), 'Fuori dai giorni letti.');
    }

    public function testWhenAnotherBookingTakesTheSeatFirstItTriesTheNextOne(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $this->bookings->place($this->customer('a@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);

        // La lettura dei posti è vecchia di un istante: il posto 1 sembra libero, ma l'ha appena preso un altro.
        $stale = new class implements SeatChooser {
            public int $calls = 0;

            public function choose(array $taken, int $capacity): ?int
            {
                ++$this->calls;

                return $this->calls === 1 ? 1 : FirstFreeSeat::among($taken, $capacity);
            }
        };
        $result = (new PdoBookingRepository($this->database, $stale))->place($this->customer('b@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);

        self::assertSame(BookingStatus::Booked, $result->status);
        self::assertSame(2, $result->booking()->seat());
        self::assertSame(2, $stale->calls, 'Il vincolo del database ha respinto il posto 1: un secondo tentativo, con una lettura nuova.');
        self::assertSame([1, 2], $this->bookings->occupancy($slot->date, $slot->date)->seatsOf($slot->date, $slot->time));
    }

    public function testItGivesUpLoudlyIfTheSeatKeepsBeingTaken(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $this->bookings->place($this->customer('a@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);
        $alwaysOne = new class implements SeatChooser {
            public function choose(array $taken, int $capacity): int
            {
                return 1;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nessun posto assegnato nella fascia 2026-12-07 18:30 dopo 7 tentativi.');

        (new PdoBookingRepository($this->database, $alwaysOne))->place($this->customer('b@example.com')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);
    }

    public function testTheDatabaseItselfKeepsOneBookingPerSeatAndOneActivePerDay(): void
    {
        $anna = $this->customer('anna@example.com');
        $luca = $this->customer('luca@example.com');
        $row = ['slot_date' => '2026-12-07', 'slot_time' => '18:30', 'starts_at' => '2026-12-07 17:30:00', 'created_at' => '2026-11-25 07:00:00', 'created_by' => 'customer'];
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

    public function testSomeoneWithoutAnAccountTakesTheFirstFreeSeatOnceBySlot(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $staff = $this->customer('titolare@example.test');
        $this->bookings->place($this->customer('a@example.test')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Customer);

        $first = $this->bookings->placeGuest(self::person('Marco Neri'), $slot, 5, $staff->id, $this->clock->now());
        self::assertSame(BookingStatus::Booked, $first->status);
        $guest = $first->booking();
        self::assertSame(2, $guest->seat());
        self::assertNull($guest->userId);
        self::assertSame('Marco Neri', $guest->guestName);
        self::assertTrue($guest->isGuest());
        self::assertSame(CreatedBy::Admin, $guest->createdBy);
        self::assertFalse($guest->belongsTo($staff->id), 'Di nessun account.');

        // Lo stesso nome nella stessa fascia è la stessa persona; un altro nome, o un'altra fascia, no.
        $again = $this->bookings->placeGuest(self::person('Marco Neri'), $slot, 5, $staff->id, $this->clock->now());
        self::assertSame(BookingStatus::AlreadyBooked, $again->status);
        self::assertTrue($again->isRepeat());
        self::assertSame($guest->id, $again->booking()->id);
        self::assertSame(3, $this->bookings->placeGuest(self::person('Marco Bianchi'), $slot, 5, $staff->id, $this->clock->now())->booking()->seat());
        self::assertSame(BookingStatus::Booked, $this->bookings->placeGuest(self::person('Marco Neri'), $this->slot('2026-12-07', '20:00'), 5, $staff->id, $this->clock->now())->status);

        // Disdetta, si reinserisce; e i posti valgono anche qui.
        self::assertTrue($this->bookings->cancel($guest->id, $this->clock->now(), CancelledBy::Admin));
        self::assertSame(2, $this->bookings->placeGuest(self::person('Marco Neri'), $slot, 5, $staff->id, $this->clock->now())->booking()->seat());
        self::assertSame(BookingStatus::Booked, $this->bookings->placeGuest(self::person('Quarta Persona'), $slot, 5, $staff->id, $this->clock->now())->status);
        self::assertSame(BookingStatus::Booked, $this->bookings->placeGuest(self::person('Quinta Persona'), $slot, 5, $staff->id, $this->clock->now())->status);
        self::assertSame(BookingStatus::Full, $this->bookings->placeGuest(self::person('Sesta Persona'), $slot, 5, $staff->id, $this->clock->now())->status);
        self::assertSame(BookingStatus::Full, $this->bookings->place($this->customer('f@example.test')->id, $slot, 5, null, $this->clock->now(), CreatedBy::Admin)->status);
        self::assertSame([1, 2, 3, 4, 5], $this->bookings->occupancy($slot->date, $slot->date)->seatsOf($slot->date, $slot->time));
    }

    public function testWhenTheSamePersonIsWrittenInTheMeanTimeTheNextAttemptFindsIt(): void
    {
        $slot = $this->slot('2026-12-07', '18:30');
        $staff = $this->customer('titolare@example.test');

        // Fra la lettura e la scrittura un altro invio dello stesso modulo scrive la stessa persona (su un altro posto):
        // il database respinge la seconda riga, e il tentativo successivo, con una lettura nuova, trova la prima.
        $concurrent = new class ($this->database) implements SeatChooser {
            public int $calls = 0;

            public function __construct(
                private readonly Database $database,
            ) {}

            public function choose(array $taken, int $capacity): int
            {
                ++$this->calls;
                $this->database->execute(
                    'INSERT INTO bookings (user_id, guest_name, slot_date, slot_time, starts_at, seat, active, created_at, created_by)'
                    . " VALUES (NULL, 'Marco Neri', '2026-12-07', '18:30', '2026-12-07 17:30:00', 1, 1, '2026-11-25 07:00:00', 'admin')",
                );

                return 2;
            }
        };

        $result = (new PdoBookingRepository($this->database, $concurrent))->placeGuest(self::person('Marco Neri'), $slot, 5, $staff->id, $this->clock->now());

        self::assertSame(BookingStatus::AlreadyBooked, $result->status);
        self::assertTrue($result->isRepeat());
        self::assertSame(1, $concurrent->calls);
        self::assertSame([1], $this->bookings->occupancy($slot->date, $slot->date)->seatsOf($slot->date, $slot->time), 'Una riga sola.');
    }

    public function testTheDatabaseItselfKeepsOneGuestPerNameInASlot(): void
    {
        $row = ['user_id' => null, 'slot_date' => '2026-12-07', 'slot_time' => '18:30', 'starts_at' => '2026-12-07 17:30:00', 'created_at' => '2026-11-25 07:00:00', 'created_by' => 'admin'];
        $this->insert([...$row, 'guest_name' => 'Marco Neri', 'seat' => 1, 'active' => 1]);

        try {
            $this->insert([...$row, 'guest_name' => 'Marco Neri', 'seat' => 2, 'active' => 1]);
            self::fail('Il database doveva rifiutare lo stesso nome attivo nella stessa fascia.');
        } catch (PDOException) {
            // Rifiutata, come deve.
        }

        // Il confronto è esatto (le maiuscole contano), e le disdette non contano.
        $this->insert([...$row, 'guest_name' => 'marco neri', 'seat' => 2, 'active' => 1]);
        $this->insert([...$row, 'guest_name' => 'Marco Neri', 'seat' => null, 'active' => null, 'cancelled_at' => '2026-11-25 07:30:00', 'cancelled_by' => 'admin']);
        self::assertSame(3, $this->database->fetchOne('SELECT COUNT(*) AS total FROM bookings')?->int('total'));
    }

    public function testTheRosterOfADayHasThePeopleInOneRead(): void
    {
        $staff = $this->customer('titolare@example.test');
        $anna = $this->customer('anna@example.test', 'Anna Bianchi');
        $luca = $this->customer('luca@example.test', 'Luca Verdi');
        $now = $this->clock->now();
        $this->bookings->place($luca->id, $this->slot('2026-12-07', '18:30'), 5, null, $now, CreatedBy::Admin);
        $this->bookings->placeGuest(self::person('Marco Neri'), $this->slot('2026-12-07', '07:00'), 5, $staff->id, $now);
        $annas = $this->bookings->place($anna->id, $this->slot('2026-12-07', '07:00'), 5, null, $now, CreatedBy::Customer)->booking();
        $cancelled = $this->bookings->placeGuest(self::person('Ospite Disdetto'), $this->slot('2026-12-07', '07:00'), 5, $staff->id, $now)->booking();
        $this->bookings->cancel($cancelled->id, $now, CancelledBy::Admin);
        $this->bookings->place($anna->id, $this->slot('2026-12-08', '07:00'), 5, null, $now, CreatedBy::Customer);

        $roster = $this->bookings->roster(self::day('2026-12-07'));

        // Per fascia e per posto, solo le attive di quel giorno.
        self::assertSame(
            [['07:00', 1, 'Marco Neri', null], ['07:00', 2, 'Anna Bianchi', 'anna@example.test'], ['18:30', 1, 'Luca Verdi', 'luca@example.test']],
            array_map(static fn(RosterEntry $entry): array => [$entry->booking->time->value, $entry->booking->seat(), $entry->name, $entry->email?->value], $roster),
        );
        self::assertCount(3, $roster);
        self::assertSame(CreatedBy::Admin, $roster[2]->booking->createdBy);
        self::assertSame([], $this->bookings->roster(self::day('2026-12-09')));

        // Una sola prenotazione con la sua persona, anche disdetta (per l'avviso del pannello).
        self::assertSame('Anna Bianchi', $this->bookings->findEntry($annas->id)?->name);
        $entry = $this->bookings->findEntry($cancelled->id);
        self::assertSame('Ospite Disdetto', $entry?->name);
        self::assertFalse($entry->booking->isActive());
        self::assertNull($this->bookings->findEntry(PHP_INT_MAX));
    }

    public function testTheMonthAndTheScheduleChangesReadTheBookingsInOneQuery(): void
    {
        $staff = $this->customer('titolare@example.test');
        $anna = $this->customer('anna@example.test', 'Anna Bianchi');
        $luca = $this->customer('luca@example.test', 'Luca Verdi');
        $now = $this->clock->now();
        $first = $this->bookings->place($anna->id, $this->slot('2026-12-07', '07:00'), 5, null, $now, CreatedBy::Customer)->booking();
        $guest = $this->bookings->placeGuest(self::person('Marco Neri'), $this->slot('2026-12-07', '07:00'), 5, $staff->id, $now)->booking();
        $evening = $this->bookings->place($luca->id, $this->slot('2026-12-07', '18:30'), 5, null, $now, CreatedBy::Customer)->booking();
        $cancelled = $this->bookings->place($anna->id, $this->slot('2026-12-09', '09:30'), 5, null, $now, CreatedBy::Customer)->booking();
        $this->bookings->cancel($cancelled->id, $now, CancelledBy::Admin);
        $later = $this->bookings->place($luca->id, $this->slot('2027-01-04', '18:30'), 5, null, $now, CreatedBy::Admin)->booking();

        // Contate dal database, per fascia, solo le attive.
        $counts = $this->bookings->slotCounts(self::day('2026-12-01'), self::day('2026-12-31'));
        self::assertSame(['07:00' => 2, '18:30' => 1], $counts->on(self::day('2026-12-07')));
        self::assertSame(0, $counts->of(self::day('2026-12-09'), self::time('09:30')));
        self::assertSame([], $counts->on(self::day('2027-01-04')), 'Fuori dal mese.');

        // Le attive con le persone, da un giorno (fino a un altro), per giorno, fascia e posto.
        $ids = self::ids(...);
        self::assertSame([$first->id, $guest->id, $evening->id], $ids($this->bookings->activeEntries(self::day('2026-12-07'), self::day('2026-12-31'))));
        self::assertSame([$first->id, $guest->id, $evening->id, $later->id], $ids($this->bookings->activeEntries(self::day('2026-12-01'))));
        self::assertSame([$later->id], $ids($this->bookings->activeEntries(self::day('2026-12-08'))));
        self::assertSame('Marco Neri', ($this->bookings->activeEntries(self::day('2026-12-07'))[1] ?? self::fail())->name);

        // Per numero, anche disdette, ognuno una volta; nessun numero, nessuna lettura.
        $found = $this->bookings->findEntries([$cancelled->id, $evening->id, $cancelled->id, PHP_INT_MAX]);
        self::assertSame([$evening->id, $cancelled->id], $ids($found));
        $cancelledEntry = $found[1] ?? self::fail();
        self::assertFalse($cancelledEntry->booking->isActive());
        self::assertSame('anna@example.test', $cancelledEntry->email?->value);
        self::assertSame([], $this->bookings->findEntries([]));
    }

    private function slot(string $date, string $time): ScheduledSlot
    {
        return $this->calendar->slot(self::day($date), self::time($time))
            ?? self::fail(sprintf('Nessuna fascia il %s alle %s.', $date, $time));
    }

    /**
     * I numeri delle prenotazioni, nell'ordine dato.
     *
     * @param list<RosterEntry> $entries
     *
     * @return list<int>
     */
    private static function ids(array $entries): array
    {
        return array_map(static fn(RosterEntry $entry): int => $entry->booking->id, $entries);
    }

    private static function day(string $value): LocalDate
    {
        return LocalDate::tryFrom($value) ?? self::fail(sprintf('Giorno non valido: %s.', $value));
    }

    private static function time(string $value): SlotTime
    {
        return SlotTime::tryFrom($value) ?? self::fail(sprintf('Orario non valido: %s.', $value));
    }

    private static function person(string $value): FullName
    {
        return FullName::tryFrom($value) ?? self::fail(sprintf('Nome non valido: %s.', $value));
    }

    private function customer(string $email, string $name = 'Cliente Prova'): User
    {
        $users = new PdoUserRepository($this->database);

        return $users->create(new NewUser(
            self::person($name),
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
