<?php

declare(strict_types=1);

namespace FourStreet\Http;

/**
 * Invio dello stato e delle intestazioni al client: l'unico punto in cui Response tocca il server.
 */
interface HeaderWriter
{
    public function headersSent(): bool;

    public function status(int $code): void;

    public function header(string $line, bool $replace): void;

    public function remove(string $name): void;
}
