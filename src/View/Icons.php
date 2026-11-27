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
        'calendar' => '<path d="M3 5h18v16H3z"></path><path d="M3 10h18M8 3v4M16 3v4"></path>',
        'navigate' => '<path d="M3 11l18-8-8 18-2-8z"></path>',
        'copy' => '<path d="M8 8h13v13H8z"></path><path d="M16 8V3H3v13h5"></path>',
        'list' => '<path d="M9 6h12M9 12h12M9 18h12"></path><path d="M3 6h2M3 12h2M3 18h2"></path>',
        // Il secondo tratto è quello verticale: le domande frequenti lo nascondono quando la risposta è aperta.
        'plus' => '<path d="M5 12h14"></path><path d="M12 5v14"></path>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path>',
        'whatsapp' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"></path>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"></path><circle cx="12" cy="9.5" r="2.5"></circle>',
        'clock' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 12l3 2"></path><path class="fs-anim-tick" d="M12 12V7"></path>',
        'bag' => '<path d="M5 8h14l-1 13H6z"></path><path d="M9 8V6a3 3 0 0 1 6 0v2"></path>',
        // Avvisi e moduli dell'area clienti.
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"></path>',
        'alert' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7.5v6"></path><path d="M12 16.5h.01"></path>',
        'info' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 10.5v6"></path><path d="M12 7.5h.01"></path>',
        'mail' => '<path d="M3 5h18v14H3z"></path><path d="M3 6l9 7 9-7"></path>',
        'exit' => '<path d="M14 4h6v16h-6"></path><path d="M10 8l-4 4 4 4"></path><path d="M6 12h10"></path>',
        // Il link all'area clienti.
        'user' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path>',
        // Il giorno prima e il giorno dopo, nel pannello del titolare.
        'previous' => '<path d="M15 5l-7 7 7 7"></path>',
        'next' => '<path d="M9 5l7 7-7 7"></path>',
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
