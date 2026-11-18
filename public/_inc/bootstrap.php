<?php
declare(strict_types=1);

$FS_CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set($FS_CONFIG['timezone']);

function fs_config(string $key)
{
    global $FS_CONFIG;
    return $FS_CONFIG[$key] ?? null;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Anteprime per provare gli stati prima dell'apertura: ?anteprima=mappa oppure ?anteprima=zero */
function fs_preview(): string
{
    $preview = isset($_GET['anteprima']) && is_string($_GET['anteprima']) ? $_GET['anteprima'] : '';
    return in_array($preview, ['mappa', 'zero'], true) ? $preview : '';
}

function fs_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now');
}

/** Data vera dell'inaugurazione. */
function fs_opening(): DateTimeImmutable
{
    static $opening = null;
    if ($opening === null) {
        $opening = new DateTimeImmutable((string) fs_config('opening'));
    }
    return $opening;
}

/** Istante in cui scade il countdown (in anteprima "zero" è tra pochi secondi). */
function fs_countdown_target(): DateTimeImmutable
{
    static $target = null;
    if ($target === null) {
        $target = fs_preview() === 'zero' ? fs_now()->modify('+12 seconds') : fs_opening();
    }
    return $target;
}

function fs_is_live(): bool
{
    if (fs_preview() === 'mappa') {
        return true;
    }
    return fs_now() >= fs_countdown_target();
}

/** Dal giorno dopo l'inaugurazione il sito parla di una palestra aperta, non di un evento in corso. */
function fs_event_is_over(): bool
{
    return fs_now() >= fs_opening()->modify('tomorrow');
}

function fs_seconds_left(): int
{
    return max(0, fs_countdown_target()->getTimestamp() - fs_now()->getTimestamp());
}

function fs_date_it(DateTimeImmutable $date, bool $withYear = true, bool $capital = true): string
{
    $days = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    $months = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    $text = $days[(int) $date->format('w')] . ' ' . (int) $date->format('j') . ' ' . $months[(int) $date->format('n')];
    if ($withYear) {
        $text .= ' ' . $date->format('Y');
    }
    return $capital ? ucfirst($text) : $text;
}

/** Orario dell'inaugurazione, solo se confermato in config. */
function fs_opening_time(): string
{
    return fs_config('opening_time_confirmed') ? fs_opening()->format('H:i') : '';
}

function fs_link(string $name): string
{
    $links = fs_config('links');
    return is_array($links) && !empty($links[$name]) ? (string) $links[$name] : '';
}

function fs_maps_url(): string
{
    $custom = (string) fs_config('maps_url');
    if ($custom !== '') {
        return $custom;
    }
    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode((string) fs_config('address'));
}

function fs_apple_maps_url(): string
{
    return 'https://maps.apple.com/?daddr=' . rawurlencode((string) fs_config('address'));
}

function fs_maps_embed_url(): string
{
    return 'https://www.google.com/maps?q=' . rawurlencode((string) fs_config('address')) . '&z=16&hl=it&output=embed';
}

/** URL di un file statico con versione, così il browser non tiene copie vecchie dopo un deploy. */
function fs_asset(string $path): string
{
    $file = dirname(__DIR__) . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function fs_icon(string $name, string $class = ''): string
{
    $paths = [
        'arrow' => '<path d="M7 17 17 7"></path><path d="M8 7h9v9"></path>',
        'calendar-add' => '<path d="M3 5h18v16H3z"></path><path d="M3 10h18M8 3v4M16 3v4"></path><path d="M12 13v5M9.5 15.5h5"></path>',
        'calendar' => '<path d="M3 5h18v16H3z"></path><path d="M3 10h18M8 3v4M16 3v4"></path>',
        'navigate' => '<path d="M3 11l18-8-8 18-2-8z"></path>',
        'copy' => '<path d="M8 8h13v13H8z"></path><path d="M16 8V3H3v13h5"></path>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path>',
        'whatsapp' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"></path>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"></path><circle cx="12" cy="9.5" r="2.5"></circle>',
        'clock' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 12l3 2"></path><path class="fs-anim-tick" d="M12 12V7"></path>',
        'bag' => '<path d="M5 8h14l-1 13H6z"></path><path d="M9 8V6a3 3 0 0 1 6 0v2"></path>',
    ];
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<svg' . $classAttr . ' width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}
