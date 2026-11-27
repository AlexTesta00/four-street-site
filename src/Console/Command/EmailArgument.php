<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Account\EmailAddress;
use FourStreet\Console\Output;

/**
 * L'argomento <email> dei comandi sui ruoli: uno solo, un indirizzo valido (normalizzato come nella registrazione).
 * Se manca o è sbagliato, scrive cosa fare.
 */
final class EmailArgument
{
    /**
     * @param list<string> $arguments gli argomenti dopo il nome del comando
     */
    public static function read(array $arguments, string $command, Output $output): ?EmailAddress
    {
        $usage = sprintf('Uso: php bin/console %s <email>', $command);
        if (count($arguments) !== 1) {
            $output->error(($arguments === [] ? 'Manca l\'email dell\'account. ' : 'Un\'email alla volta. ') . $usage);

            return null;
        }

        $email = EmailAddress::tryFrom($arguments[0]);
        if ($email === null) {
            $output->error(sprintf('Email non valida: "%s". %s', $arguments[0], $usage));
        }

        return $email;
    }
}
