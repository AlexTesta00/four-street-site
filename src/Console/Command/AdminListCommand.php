<?php

declare(strict_types=1);

namespace FourStreet\Console\Command;

use FourStreet\Account\AdminRoles;
use FourStreet\Console\Command;
use FourStreet\Console\Output;

/**
 * php bin/console admin:list: i titolari, uno per riga (email e nome), in ordine di email.
 */
final readonly class AdminListCommand implements Command
{
    public const string NAME = 'admin:list';
    public const string DESCRIPTION = 'Elenca i titolari (gli account con il pannello delle prenotazioni).';

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
        $admins = $this->roles->admins();
        if ($admins === []) {
            $output->line('Nessun titolare. Per nominarne uno: php bin/console admin:grant <email>');

            return 0;
        }

        foreach ($admins as $admin) {
            $output->line(sprintf('%s  %s', $admin->email, $admin->fullName));
        }

        return 0;
    }
}
