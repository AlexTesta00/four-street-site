<?php

declare(strict_types=1);

namespace FourStreet\Http;

/**
 * Usa le funzioni di PHP (header(), http_response_code()).
 */
final class NativeHeaderWriter implements HeaderWriter
{
    public function headersSent(): bool
    {
        return headers_sent();
    }

    public function status(int $code): void
    {
        http_response_code($code);
    }

    public function header(string $line, bool $replace): void
    {
        header($line, $replace);
    }

    public function remove(string $name): void
    {
        header_remove($name);
    }
}
