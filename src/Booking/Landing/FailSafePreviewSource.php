<?php

declare(strict_types=1);

namespace FourStreet\Booking\Landing;

use FourStreet\Error\ExceptionReporter;
use Throwable;

/**
 * Il guscio delle fasce della landing: qualunque errore della fonte vera (database spento, tabella che
 * manca, dati inattesi) si registra e la pagina mostra la versione senza numeri; se anche quella non
 * riesce, le fasce di esempio. La home non risponde mai 500 per colpa delle prenotazioni.
 */
final readonly class FailSafePreviewSource implements SlotPreviewSource
{
    /**
     * @param SlotPreviewSource $primary le fasce con i posti veri
     * @param SlotPreviewSource $fallback senza numeri, dal solo calendario
     * @param SlotPreviewSource $lastResort le fasce di esempio
     */
    public function __construct(
        private SlotPreviewSource $primary,
        private SlotPreviewSource $fallback,
        private SlotPreviewSource $lastResort,
        private ExceptionReporter $reporter,
    ) {}

    public function preview(): SlotPreview
    {
        foreach ([$this->primary, $this->fallback] as $source) {
            try {
                return $source->preview();
            } catch (Throwable $error) {
                $this->reporter->report($error);
            }
        }

        return $this->lastResort->preview();
    }

    /** Anche la versione di riserva va ricalcolata: appena il database torna, tornano i posti. */
    public function isLive(): bool
    {
        return true;
    }
}
