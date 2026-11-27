<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Le fasce di un giorno da scegliere con il gruppo di radio della landing: quelle di esempio
 * di config/site.php (ExampleSlotCatalog) oppure quelle vere, con i posti del database (LivePreview).
 */
interface SlotCatalog
{
    /**
     * Posti di ogni fascia: il numero che regola tutta la pagina.
     *
     * @return positive-int
     */
    public function capacity(): int;

    /**
     * @return list<Slot> nell'ordine in cui compaiono
     */
    public function slots(): array;

    /**
     * La fascia scelta all'apertura della pagina: una di slots() che si può prenotare,
     * oppure null se sono tutte piene o chiuse.
     */
    public function defaultSlot(): ?Slot;
}
