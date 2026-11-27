<?php

declare(strict_types=1);

namespace FourStreet\Account;

use DateTimeImmutable;
use FourStreet\Database\Database;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use PDOException;
use UnexpectedValueException;

/**
 * Gli account nella tabella users.
 * Ogni segnaposto compare una volta sola per istruzione: MySQL, senza emulazione, non ne accetta di ripetuti.
 */
final readonly class PdoUserRepository implements UserRepository
{
    private const string COLUMNS = 'id, full_name, email, password_hash, email_verified_at, privacy_accepted_at, role, signup_browser_hash, created_at, updated_at';

    public function __construct(
        private Database $database,
    ) {}

    public function create(NewUser $user): User
    {
        $at = UtcDateTime::format($user->at);
        try {
            $this->database->execute(
                'INSERT INTO users (full_name, email, password_hash, email_verified_at, privacy_accepted_at, role, signup_browser_hash, created_at, updated_at)'
                . ' VALUES (:full_name, :email, :password_hash, NULL, :privacy_accepted_at, :role, :signup_browser_hash, :created_at, :updated_at)',
                [
                    'full_name' => $user->fullName->value,
                    'email' => $user->email->value,
                    'password_hash' => $user->passwordHash,
                    'privacy_accepted_at' => $at,
                    'role' => Role::Customer->value,
                    'signup_browser_hash' => $user->browserHash,
                    'created_at' => $at,
                    'updated_at' => $at,
                ],
            );
        } catch (PDOException $error) {
            // Violazione di un vincolo (23000) con l'email ormai presente: un'altra registrazione è arrivata prima.
            if (($error->errorInfo[0] ?? null) === '23000' && $this->findByEmail($user->email) !== null) {
                throw new DuplicateEmail(sprintf('L\'email %s ha già un account.', $user->email), 0, $error);
            }

            throw $error;
        }

        return $this->findById($this->database->lastInsertId())
            ?? throw new UnexpectedValueException('L\'account appena creato non si trova.');
    }

    public function findById(int $id): ?User
    {
        $row = $this->database->fetchOne('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id', ['id' => $id]);

        return $row === null ? null : self::user($row);
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        $row = $this->database->fetchOne('SELECT ' . self::COLUMNS . ' FROM users WHERE email = :email', ['email' => $email->value]);

        return $row === null ? null : self::user($row);
    }

    public function findByRole(Role $role): array
    {
        return array_map(self::user(...), $this->database->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE role = :role ORDER BY email',
            ['role' => $role->value],
        ));
    }

    public function replaceUnverified(int $id, NewUser $registration): void
    {
        $at = UtcDateTime::format($registration->at);
        $this->database->execute(
            'UPDATE users SET full_name = :full_name, password_hash = :password_hash, privacy_accepted_at = :privacy_accepted_at,'
            . ' signup_browser_hash = :signup_browser_hash, updated_at = :updated_at WHERE id = :id AND email_verified_at IS NULL',
            [
                'full_name' => $registration->fullName->value,
                'password_hash' => $registration->passwordHash,
                'privacy_accepted_at' => $at,
                'signup_browser_hash' => $registration->browserHash,
                'updated_at' => $at,
                'id' => $id,
            ],
        );
    }

    public function markEmailVerified(User $user, DateTimeImmutable $at): bool
    {
        // La password letta dice se la riga è ancora quella: una registrazione nuova ne scrive sempre un'altra.
        return $this->database->execute(
            'UPDATE users SET email_verified_at = :email_verified_at, signup_browser_hash = NULL, updated_at = :updated_at'
            . ' WHERE id = :id AND email_verified_at IS NULL AND password_hash = :password_hash',
            [
                'email_verified_at' => UtcDateTime::format($at),
                'updated_at' => UtcDateTime::format($at),
                'id' => $user->id,
                'password_hash' => $user->passwordHash,
            ],
        ) === 1;
    }

    public function updatePasswordHash(int $id, string $passwordHash, DateTimeImmutable $at): void
    {
        $this->database->execute(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id',
            ['password_hash' => $passwordHash, 'updated_at' => UtcDateTime::format($at), 'id' => $id],
        );
    }

    public function updateRole(int $id, Role $role, DateTimeImmutable $at): void
    {
        $this->database->execute(
            'UPDATE users SET role = :role, updated_at = :updated_at WHERE id = :id',
            ['role' => $role->value, 'updated_at' => UtcDateTime::format($at), 'id' => $id],
        );
    }

    private static function user(Row $row): User
    {
        $email = $row->string('email');
        $role = $row->string('role');

        return new User(
            id: $row->int('id'),
            fullName: $row->string('full_name'),
            email: EmailAddress::tryFrom($email) ?? throw new UnexpectedValueException(sprintf('Email non valida nel database: "%s".', $email)),
            passwordHash: $row->string('password_hash'),
            emailVerifiedAt: $row->nullableDateTime('email_verified_at'),
            privacyAcceptedAt: $row->dateTime('privacy_accepted_at'),
            role: Role::tryFrom($role) ?? throw new UnexpectedValueException(sprintf('Ruolo sconosciuto nel database: "%s".', $role)),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
            signupBrowserHash: $row->nullableString('signup_browser_hash'),
        );
    }
}
