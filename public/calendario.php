<?php
// Evento dell'inaugurazione da aggiungere al calendario (.ics).
require __DIR__ . '/_inc/bootstrap.php';

function fs_ics_text(string $text): string
{
    return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
}

$opening = fs_opening();
$utc = new DateTimeZone('UTC');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//4Street//Inaugurazione//IT',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:inaugurazione-' . $opening->format('Ymd') . '@4street',
    'DTSTAMP:' . fs_now()->setTimezone($utc)->format('Ymd\THis\Z'),
];

if (fs_config('opening_time_confirmed')) {
    $lines[] = 'DTSTART:' . $opening->setTimezone($utc)->format('Ymd\THis\Z');
} else {
    // Orario non ancora confermato: evento di tutto il giorno.
    $lines[] = 'DTSTART;VALUE=DATE:' . $opening->format('Ymd');
    $lines[] = 'DTEND;VALUE=DATE:' . $opening->modify('+1 day')->format('Ymd');
}

$lines[] = 'SUMMARY:' . fs_ics_text('Inaugurazione 4Street');
$lines[] = 'LOCATION:' . fs_ics_text((string) fs_config('address'));
$lines[] = 'END:VEVENT';
$lines[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="inaugurazione-4street.ics"');
header('Cache-Control: no-store');
echo implode("\r\n", $lines) . "\r\n";
