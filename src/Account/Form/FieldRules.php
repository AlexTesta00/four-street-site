<?php

declare(strict_types=1);

namespace FourStreet\Account\Form;

use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;

/**
 * I messaggi di validazione condivisi dai moduli dell'area clienti: brevi, e dicono cosa fare.
 */
final class FieldRules
{
    public static function emailProblem(string $input): ?string
    {
        return match (true) {
            trim($input) === '' => 'Scrivi la tua email.',
            EmailAddress::tryFrom($input) === null => 'Scrivi un indirizzo email valido, come nome@esempio.it.',
            default => null,
        };
    }

    public static function nameProblem(string $input): ?string
    {
        $name = FullName::normalize($input);

        return match (true) {
            $name === '' => 'Scrivi nome e cognome.',
            mb_strlen($name) > FullName::MAX_LENGTH => sprintf('Nome e cognome possono avere al massimo %d caratteri.', FullName::MAX_LENGTH),
            FullName::tryFrom($input) === null => 'Scrivi nome e cognome per intero.',
            default => null,
        };
    }
}
