<?php

declare(strict_types=1);

namespace FourStreet\View;

use InvalidArgumentException;

/**
 * Icone SVG in linea, a tratto, 24×24.
 */
final class Icons
{
    private const array PATHS = [
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

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys(self::PATHS);
    }

    public function svg(string $name, string $class = ''): Html
    {
        $paths = self::PATHS[$name] ?? throw new InvalidArgumentException(sprintf('Icona sconosciuta: "%s".', $name));
        $classAttribute = $class !== '' ? ' class="' . Escaper::html($class) . '"' : '';

        return new Html(
            '<svg' . $classAttribute . ' width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">' . $paths . '</svg>',
        );
    }
}
