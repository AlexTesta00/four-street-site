<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Booking;

use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\FirstFreeSeat;
use FourStreet\Booking\Landing\ExamplePreviewSource;
use FourStreet\Booking\Landing\FailSafePreviewSource;
use FourStreet\Booking\Landing\LivePreview;
use FourStreet\Booking\Landing\SlotPreview;
use FourStreet\Booking\Landing\SlotPreviewSource;
use FourStreet\Booking\Landing\TimetablePreview;
use FourStreet\Booking\Landing\TimetablePreviewSource;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\ScheduledSlot;
use FourStreet\Booking\Slot;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use FourStreet\Booking\WeeklySchedule;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\SiteConfig;
use FourStreet\Tests\Support\CollectingReporter;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Text\ItalianDateFormatter;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Le fasce della landing: il guscio che non lascia mai cadere la pagina, gli orari senza numeri,
 * gli esempi e la fascia scelta all'apertura.
 */
#[CoversClass(FailSafePreviewSource::class)]
#[CoversClass(TimetablePreviewSource::class)]
#[CoversClass(TimetablePreview::class)]
#[CoversClass(ExamplePreviewSource::class)]
#[CoversClass(LivePreview::class)]
#[UsesClass(BookingCalendar::class)]
#[UsesClass(BookingRules::class)]
#[UsesClass(WeeklySchedule::class)]
#[UsesClass(ExampleSlotCatalog::class)]
#[UsesClass(ScheduledSlot::class)]
#[UsesClass(LocalDate::class)]
#[UsesClass(SlotTime::class)]
#[UsesClass(Weekday::class)]
#[UsesClass(Slot::class)]
#[UsesClass(FirstFreeSeat::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(SiteConfig::class)]
#[UsesClass(ItalianDateFormatter::class)]
final class LandingPreviewTest extends TestCase
{
    public function testTheShellFallsBackToTheTimetableAndReportsTheError(): void
    {
        $reporter = new CollectingReporter();
        $shell = new FailSafePreviewSource(self::broken('Database spento.'), $this->timetable('2026-11-25 08:00:00'), $this->examples(), $reporter);

        $preview = $shell->preview();

        self::assertInstanceOf(TimetablePreview::class, $preview);
        self::assertSame('2026-12-07', $preview->day?->value);
        self::assertSame('lunedì 7 dicembre', $preview->dayLabel);
        self::assertSame(['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'], array_map(strval(...), $preview->times));
        self::assertSame(5, $preview->capacity());
        self::assertTrue($shell->isLive());
        self::assertCount(1, $reporter->reported);
        self::assertSame('Database spento.', $reporter->reported[0]->getMessage());
    }

    public function testIfEvenTheTimetableFailsTheExamplesRemain(): void
    {
        $reporter = new CollectingReporter();
        $shell = new FailSafePreviewSource(self::broken('Uno.'), self::broken('Due.'), $this->examples(), $reporter);

        self::assertInstanceOf(ExampleSlotCatalog::class, $shell->preview());
        self::assertSame(['Uno.', 'Due.'], array_map(static fn(Throwable $error): string => $error->getMessage(), $reporter->reported));
    }

    public function testWhenTheRealSourceWorksNothingIsReported(): void
    {
        $reporter = new CollectingReporter();
        $live = new LivePreview(5, LocalDate::tryFrom('2026-12-07'), 'lunedì 7 dicembre', [Slot::example('07:00', 5, 1)]);
        $source = new class ($live) implements SlotPreviewSource {
            public function __construct(
                private readonly SlotPreview $preview,
            ) {}

            public function preview(): SlotPreview
            {
                return $this->preview;
            }

            public function isLive(): bool
            {
                return true;
            }
        };

        self::assertSame($live, (new FailSafePreviewSource($source, self::broken('Mai.'), $this->examples(), $reporter))->preview());
        self::assertSame([], $reporter->reported);
    }

    public function testTheTimetableSkipsWhatHasStarted(): void
    {
        // Lunedì 7 alle 18:45: restano le 20:00.
        $preview = $this->timetable('2026-12-07 18:45:00')->preview();
        self::assertInstanceOf(TimetablePreview::class, $preview);
        self::assertSame('2026-12-07', $preview->day?->value);
        self::assertSame(['20:00'], array_map(strval(...), $preview->times));

        // Alle 20:00 tocca a martedì.
        $tomorrow = $this->timetable('2026-12-07 20:00:00')->preview();
        self::assertInstanceOf(TimetablePreview::class, $tomorrow);
        self::assertSame('2026-12-08', $tomorrow->day?->value);
    }

    public function testTheTimetableIsEmptyWhenNothingIsBookableYet(): void
    {
        $calendar = BookingCalendar::forSite(SiteFixture::config(['booking' => SiteFixture::booking(['days_ahead' => 3])]));
        $preview = (new TimetablePreviewSource($calendar, new ItalianDateFormatter(), FrozenClock::at('2026-11-25 08:00:00')))->preview();

        self::assertInstanceOf(TimetablePreview::class, $preview);
        self::assertNull($preview->day);
        self::assertSame([], $preview->times);
    }

    public function testTheExamplesAreNotLive(): void
    {
        $source = $this->examples();

        self::assertFalse($source->isLive());
        self::assertSame(5, $source->preview()->capacity());
    }

    public function testTheChosenLiveSlotIsTheFirstBookableOne(): void
    {
        $slots = [
            new Slot('07:00', 5, [], closed: true),
            new Slot('09:30', 5, [1, 2, 3, 4, 5]),
            new Slot('12:30', 5, [2]),
            new Slot('17:30', 5),
        ];

        $preview = new LivePreview(5, LocalDate::tryFrom('2026-12-07'), 'lunedì 7 dicembre', $slots);
        $chosen = $preview->defaultSlot();
        self::assertNotNull($chosen);
        self::assertSame('12:30', $chosen->time);
        self::assertSame(1, $chosen->nextSeat());
        self::assertSame($slots, $preview->slots());
        self::assertSame(5, $preview->capacity());

        self::assertNull((new LivePreview(5, null, '', []))->defaultSlot());
        self::assertNull((new LivePreview(5, LocalDate::tryFrom('2026-12-07'), 'lunedì 7 dicembre', array_slice($slots, 0, 2)))->defaultSlot());
    }

    private function timetable(string $now): TimetablePreviewSource
    {
        return new TimetablePreviewSource(BookingCalendar::forSite(SiteFixture::config()), new ItalianDateFormatter(), FrozenClock::at($now));
    }

    private function examples(): ExamplePreviewSource
    {
        return new ExamplePreviewSource(SiteFixture::config()->booking->examples);
    }

    private static function broken(string $message): SlotPreviewSource
    {
        return new class ($message) implements SlotPreviewSource {
            public function __construct(
                private readonly string $message,
            ) {}

            public function preview(): SlotPreview
            {
                throw $this->message === 'Mai.' ? new LogicException($this->message) : new RuntimeException($this->message);
            }

            public function isLive(): bool
            {
                return true;
            }
        };
    }
}
