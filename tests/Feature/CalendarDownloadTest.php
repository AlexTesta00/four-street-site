<?php

declare(strict_types=1);

namespace FourStreet\Tests\Feature;

use FourStreet\Application;
use FourStreet\Controller\CalendarController;
use FourStreet\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(CalendarController::class)]
final class CalendarDownloadTest extends TestCase
{
    private const string NOW = '2026-11-25 08:00:00';

    public function testDownloadsTheOpeningAsAnIcsFile(): void
    {
        $response = SiteFixture::handle('GET', '/calendario.php', self::NOW);

        self::assertSame(200, $response->status);
        self::assertSame('text/calendar; charset=utf-8', $response->header('Content-Type'));
        self::assertSame('attachment; filename="inaugurazione-4street.ics"', $response->header('Content-Disposition'));
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame(
            "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//4Street//Inaugurazione//IT\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:PUBLISH\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:inaugurazione-20261206@4street\r\n"
            . "DTSTAMP:20261125T070000Z\r\n"
            . "DTSTART:20261206T140000Z\r\n"
            . "SUMMARY:Inaugurazione 4Street\r\n"
            . "LOCATION:Via del Bando 22\\, San Marino\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n",
            $response->body,
        );
    }

    public function testAllDayEventWhenTheTimeIsNotConfirmed(): void
    {
        $body = SiteFixture::handle('GET', '/calendario.php', self::NOW, ['opening_time_confirmed' => false])->body;

        self::assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261206\r\nDTEND;VALUE=DATE:20261207\r\n", $body);
        self::assertStringNotContainsString('DTSTART:', $body);
    }

    public function testThePreviewsDoNotChangeTheCalendar(): void
    {
        self::assertSame(
            SiteFixture::handle('GET', '/calendario.php', self::NOW)->body,
            SiteFixture::handle('GET', '/calendario.php?anteprima=zero', self::NOW)->body,
        );
    }

    public function testHeadAndOtherMethods(): void
    {
        $head = SiteFixture::handle('HEAD', '/calendario.php', self::NOW);
        $post = SiteFixture::handle('POST', '/calendario.php', self::NOW);

        self::assertSame(200, $head->status);
        self::assertSame('', $head->body);
        self::assertSame('text/calendar; charset=utf-8', $head->header('Content-Type'));
        self::assertSame(405, $post->status);
        self::assertSame('GET, HEAD', $post->header('Allow'));
    }
}
