<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

/**
 * Da dove vengono le fasce della landing. La landing non deve mai dipendere dal database: la fonte
 * con i posti veri sta sempre dentro FailSafePreviewSource.
 */
interface SlotPreviewSource
{
    public function preview(): SlotPreview;

    /** Le fasce cambiano con le prenotazioni: la pagina non va tenuta in cache. */
    public function isLive(): bool;
}
