<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

/**
 * Cosa mostra la sezione "Come funziona" della landing: le fasce di esempio (ExampleSlotCatalog),
 * quelle vere con i posti (LivePreview) oppure, se il database non risponde, solo gli orari (TimetablePreview).
 */
interface SlotPreview
{
    /**
     * Posti di ogni fascia: la cifra grande e i quadrati della landing partono da qui.
     *
     * @return positive-int
     */
    public function capacity(): int;
}
