<?php

declare(strict_types=1);

namespace FourStreet\View;

use Stringable;

/**
 * Markup HTML già sicuro (prodotto da un template, da un'icona o già passato dall'escape).
 * I testi normali sono stringhe e vanno sempre passati da Escaper::html().
 */
final readonly class Html implements Stringable
{
    public function __construct(
        private string $markup,
    ) {}

    public function __toString(): string
    {
        return $this->markup;
    }
}
