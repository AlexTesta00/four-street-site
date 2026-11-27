<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Account\Session\SessionRepository;
use FourStreet\Clock\Clock;

/**
 * Il ruolo di titolare (admin): si dà e si toglie solo dalla console, mai da una pagina web, e solo a un account
 * che esiste già. Per darlo l'email dev'essere confermata: chiunque può registrare un indirizzo non suo, e
 * un account in sospeso non deve poter vedere i clienti. Togliere il ruolo chiude le sessioni dell'account su
 * ogni dispositivo: chi rientra lo fa da cliente (il ruolo si rilegge comunque a ogni pagina).
 */
final readonly class AdminRoles
{
    public function __construct(
        private UserRepository $users,
        private SessionRepository $sessions,
        private Clock $clock,
    ) {}

    public function grant(EmailAddress $email): RoleChange
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return RoleChange::UnknownAccount;
        }
        if (!$user->isVerified()) {
            return RoleChange::Unverified;
        }
        if ($user->role === Role::Admin) {
            return RoleChange::Unchanged;
        }

        $this->users->updateRole($user->id, Role::Admin, $this->clock->now());

        return RoleChange::Changed;
    }

    public function revoke(EmailAddress $email): RoleChange
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return RoleChange::UnknownAccount;
        }
        if ($user->role !== Role::Admin) {
            return RoleChange::Unchanged;
        }

        $this->users->updateRole($user->id, Role::Customer, $this->clock->now());
        $this->sessions->deleteForUser($user->id);

        return RoleChange::Changed;
    }

    /**
     * I titolari, in ordine di email.
     *
     * @return list<User>
     */
    public function admins(): array
    {
        return $this->users->findByRole(Role::Admin);
    }
}
