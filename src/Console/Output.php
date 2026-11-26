<?php

declare(strict_types=1);

namespace FourStreet\Console;

/**
 * Dove scrive la console: i messaggi sull'uscita standard, gli errori sull'uscita degli errori.
 */
final readonly class Output
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private mixed $stdout,
        private mixed $stderr,
    ) {}

    public function line(string $text = ''): void
    {
        fwrite($this->stdout, $text . PHP_EOL);
    }

    public function error(string $text): void
    {
        fwrite($this->stderr, $text . PHP_EOL);
    }
}
