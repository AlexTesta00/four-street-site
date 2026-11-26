<?php

declare(strict_types=1);

namespace FourStreet\Console;

/**
 * Un comando della console: php bin/console <nome> [argomenti].
 * Per aggiungerne uno: una classe con questa interfaccia, poi una riga in ConsoleApplication::create().
 */
interface Command
{
    /** Il nome con cui si lancia, per esempio "migrate". */
    public function name(): string;

    /** Una riga per l'elenco dei comandi. */
    public function description(): string;

    /**
     * @param list<string> $arguments gli argomenti dopo il nome del comando
     *
     * @return int codice d'uscita: 0 se è andato tutto bene
     */
    public function run(array $arguments, Output $output): int;
}
