<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Account\AdminRoles;
use FourStreet\Account\RoleChange;
use FourStreet\Console\Command;
use FourStreet\Console\Output;

/**
 * php bin/console admin:grant <email>: dà il ruolo di titolare a un account che esiste, con l'email confermata.
 * Il titolare entra nel pannello delle prenotazioni, /gestione/. Rilanciarlo non cambia nulla.
 */
final readonly class AdminGrantCommand implements Command
{
    public const string NAME = 'admin:grant';
    public const string DESCRIPTION = 'Dà il ruolo di titolare a un account con l\'email confermata: admin:grant <email>.';

    public function __construct(
        private AdminRoles $roles,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return self::DESCRIPTION;
    }

    public function run(array $arguments, Output $output): int
    {
        $email = EmailArgument::read($arguments, self::NAME, $output);
        if ($email === null) {
            return 1;
        }

        [$done, $message] = match ($this->roles->grant($email)) {
            RoleChange::Changed => [true, sprintf('%s ora è titolare: dopo l\'accesso trova il pannello delle prenotazioni, /gestione/.', $email)],
            RoleChange::Unchanged => [true, sprintf('%s è già titolare: nulla da cambiare.', $email)],
            RoleChange::UnknownAccount => [false, sprintf('Nessun account con l\'email %s: prima registrati dal sito e conferma l\'email, poi rilancia il comando.', $email)],
            RoleChange::Unverified => [false, sprintf('L\'account %s non ha ancora confermato l\'email: apri il link dell\'email di conferma, poi rilancia il comando.', $email)],
        };
        if (!$done) {
            $output->error($message);

            return 1;
        }
        $output->line($message);

        return 0;
    }
}
