<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

use FourStreet\Booking\ExampleSlotCatalog;

/**
 * Le fasce di esempio di config/site.php: la landing finché le prenotazioni dal sito non sono attive,
 * e l'ultima scelta se anche gli orari non si possono calcolare.
 */
final readonly class ExamplePreviewSource implements SlotPreviewSource
{
    public function __construct(
        private ExampleSlotCatalog $examples,
    ) {}

    public function preview(): SlotPreview
    {
        return $this->examples;
    }

    public function isLive(): bool
    {
        return false;
    }
}
