<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Http\HeaderWriter;

/**
 * Registra quello che Response::send() invierebbe al client.
 */
final class RecordingHeaderWriter implements HeaderWriter
{
    public ?int $status = null;

    /** @var list<array{line: string, replace: bool}> */
    public array $headers = [];

    /** @var list<string> */
    public array $removed = [];

    public function __construct(
        private readonly bool $alreadySent = false,
    ) {}

    public function headersSent(): bool
    {
        return $this->alreadySent;
    }

    public function status(int $code): void
    {
        $this->status = $code;
    }

    public function header(string $line, bool $replace): void
    {
        $this->headers[] = ['line' => $line, 'replace' => $replace];
    }

    public function remove(string $name): void
    {
        $this->removed[] = $name;
    }
}
