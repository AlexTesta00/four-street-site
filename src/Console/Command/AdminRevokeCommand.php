<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Account\AdminRoles;
use FourStreet\Account\RoleChange;
use FourStreet\Console\Command;
use FourStreet\Console\Output;

/**
 * php bin/console admin:revoke <email>: toglie il ruolo di titolare e chiude le sessioni dell'account su ogni
 * dispositivo; l'account resta, da cliente. Rilanciarlo non cambia nulla.
 */
final readonly class AdminRevokeCommand implements Command
{
    public const string NAME = 'admin:revoke';
    public const string DESCRIPTION = 'Toglie il ruolo di titolare e chiude le sessioni dell\'account: admin:revoke <email>.';

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

        [$done, $message] = match ($this->roles->revoke($email)) {
            RoleChange::Changed => [true, sprintf('%s non è più titolare: le sue sessioni sono chiuse su ogni dispositivo.', $email)],
            RoleChange::Unchanged => [true, sprintf('%s non è titolare: nulla da cambiare.', $email)],
            RoleChange::UnknownAccount, RoleChange::Unverified => [false, sprintf('Nessun account con l\'email %s.', $email)],
        };
        if (!$done) {
            $output->error($message);

            return 1;
        }
        $output->line($message);

        return 0;
    }
}
