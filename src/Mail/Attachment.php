<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use InvalidArgumentException;

/**
 * Un file allegato a un'email, per esempio l'evento da aggiungere al calendario:
 * new Attachment('prenotazione.ics', 'text/calendar; charset=utf-8; method=PUBLISH', $ics).
 */
final readonly class Attachment
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public string $content,
    ) {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/', $filename) !== 1) {
            throw new InvalidArgumentException(sprintf('Nome di allegato non valido: "%s" (lettere, cifre, punto, trattini).', $filename));
        }
        if (preg_match('#\A[a-z]+/[a-z0-9.+-]+(?:;\s*[a-z-]+=[A-Za-z0-9._-]+)*\z#', $contentType) !== 1) {
            throw new InvalidArgumentException(sprintf('Tipo di allegato non valido: "%s".', $contentType));
        }
    }
}
