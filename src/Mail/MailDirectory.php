<?php

declare(strict_types=1);

namespace FourStreet\Mail;

/**
 * Posta scritta in una cartella, un file .eml per messaggio: per lo sviluppo e per lo smoke test.
 * Un .eml si apre con un programma di posta e si vede com'è davvero, testo e HTML.
 */
final readonly class MailDirectory
{
    public function __construct(
        public string $path,
    ) {}
}
