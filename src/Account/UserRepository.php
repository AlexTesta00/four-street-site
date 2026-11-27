<?php

declare(strict_types=1);

namespace FourStreet\Account;

use DateTimeImmutable;

/**
 * Gli account salvati. I metodi dicono cosa cambia (conferma, nuova password…), mai "salva tutto".
 */
interface UserRepository
{
    /**
     * Un account nuovo, non ancora confermato, con il ruolo di cliente.
     *
     * @throws DuplicateEmail se l'email c'è già
     */
    public function create(NewUser $user): User;

    public function findById(int $id): ?User;

    public function findByEmail(EmailAddress $email): ?User;

    /**
     * Gli account con quel ruolo, in ordine di email.
     *
     * @return list<User>
     */
    public function findByRole(Role $role): array;

    /**
     * Una registrazione ripetuta prende il posto di quella non ancora confermata: nome, password,
     * accettazione dell'informativa e browser della registrazione diventano quelli nuovi.
     * Un account confermato non cambia mai così.
     */
    public function replaceUnverified(int $id, NewUser $registration): void;

    /** L'email è confermata (la prima volta conta): il browser della registrazione non serve più. */
    public function markEmailVerified(int $id, DateTimeImmutable $at): void;

    public function updatePasswordHash(int $id, string $passwordHash, DateTimeImmutable $at): void;

    /** Il ruolo dell'account: si cambia solo dalla console (titolare sì o no). */
    public function updateRole(int $id, Role $role, DateTimeImmutable $at): void;
}
