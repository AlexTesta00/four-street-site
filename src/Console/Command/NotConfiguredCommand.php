<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Console\Command;
use FourStreet\Console\Output;

/**
 * Un comando che ha bisogno del database, quando il database non è configurato (pacchetto di deploy
 * senza config/local.php): dice cosa fare invece di fallire con un errore oscuro.
 */
final readonly class NotConfiguredCommand implements Command
{
    public function __construct(
        private string $name,
        private string $description,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function run(array $arguments, Output $output): int
    {
        $output->error('Il database non è configurato: crea config/local.php partendo da config/local.example.php.');

        return 1;
    }
}
