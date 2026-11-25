<?php

declare(strict_types=1);

namespace FourStreet\Schedule;

use DateTimeImmutable;

/**
 * Stato dell'inaugurazione in un istante preciso: countdown, evento in corso, palestra aperta.
 * L'istante "adesso" è fissato una volta per richiesta, così ogni parte della pagina vede la stessa ora.
 */
final readonly class OpeningSchedule
{
    /** In anteprima "zero" il countdown scade dopo questi secondi. */
    public const int ZERO_PREVIEW_SECONDS = 12;

    /** Istante in cui scade il countdown: l'inaugurazione, oppure tra pochi secondi in anteprima "zero". */
    public DateTimeImmutable $countdownTarget;

    public function __construct(
        public DateTimeImmutable $opening,
        public DateTimeImmutable $now,
        public ?Preview $preview = null,
    ) {
        $this->countdownTarget = $preview === Preview::Zero
            ? $now->modify(sprintf('+%d seconds', self::ZERO_PREVIEW_SECONDS))
            : $opening;
    }

    /** Il countdown è scaduto (o l'anteprima "mappa" lo finge): si mostra la mappa. */
    public function isLive(): bool
    {
        return $this->preview === Preview::Map || $this->now >= $this->countdownTarget;
    }

    /** Dal giorno dopo l'inaugurazione il sito parla di una palestra aperta, non di un evento in corso. */
    public function isEventOver(): bool
    {
        return $this->now >= $this->opening->modify('tomorrow');
    }

    public function secondsLeft(): int
    {
        return max(0, $this->countdownTarget->getTimestamp() - $this->now->getTimestamp());
    }

    public function countdown(): Countdown
    {
        return new Countdown($this->secondsLeft());
    }

    /** Scadenza del countdown in millisecondi, per lo script della pagina. */
    public function countdownTargetMilliseconds(): int
    {
        return $this->countdownTarget->getTimestamp() * 1000;
    }

    /** Ora del server in millisecondi (arrotondata): lo script corregge così l'orologio del dispositivo. */
    public function nowMilliseconds(): int
    {
        return $this->now->getTimestamp() * 1000 + intdiv((int) $this->now->format('u') + 500, 1000);
    }
}
