<?php

declare(strict_types=1);

namespace FourStreet\Booking;

/**
 * Le fasce orarie da mostrare in pagina. Oggi sono esempi presi da config/site.php;
 * con le prenotazioni vere arriveranno da un catalogo che legge il database, senza cambiare i template.
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
     * @return non-empty-list<Slot> nell'ordine in cui compaiono
     */
    public function slots(): array;

    /**
     * La fascia scelta all'apertura della pagina: una di slots(), mai piena.
     */
    public function defaultSlot(): Slot;
}
