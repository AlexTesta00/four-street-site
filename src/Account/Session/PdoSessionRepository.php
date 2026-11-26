<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use DateTimeImmutable;
use FourStreet\Database\Database;
use FourStreet\Database\UtcDateTime;

/**
 * Le sessioni nella tabella sessions. Cancellare un utente cancella anche le sue sessioni (chiave esterna).
 */
final readonly class PdoSessionRepository implements SessionRepository
{
    public function __construct(
        private Database $database,
    ) {}

    public function create(Session $session): void
    {
        $this->database->execute(
            'INSERT INTO sessions (token_hash, user_id, created_at, last_seen_at, expires_at)'
            . ' VALUES (:token_hash, :user_id, :created_at, :last_seen_at, :expires_at)',
            [
                'token_hash' => $session->tokenHash,
                'user_id' => $session->userId,
                'created_at' => UtcDateTime::format($session->createdAt),
                'last_seen_at' => UtcDateTime::format($session->lastSeenAt),
                'expires_at' => UtcDateTime::format($session->expiresAt),
            ],
        );
    }

    public function find(string $tokenHash): ?Session
    {
        $row = $this->database->fetchOne(
            'SELECT token_hash, user_id, created_at, last_seen_at, expires_at FROM sessions WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );

        return $row === null ? null : new Session(
            $row->string('token_hash'),
            $row->int('user_id'),
            $row->dateTime('created_at'),
            $row->dateTime('last_seen_at'),
            $row->dateTime('expires_at'),
        );
    }

    public function touch(string $tokenHash, DateTimeImmutable $lastSeenAt, DateTimeImmutable $expiresAt): void
    {
        $this->database->execute(
            'UPDATE sessions SET last_seen_at = :last_seen_at, expires_at = :expires_at WHERE token_hash = :token_hash',
            [
                'last_seen_at' => UtcDateTime::format($lastSeenAt),
                'expires_at' => UtcDateTime::format($expiresAt),
                'token_hash' => $tokenHash,
            ],
        );
    }

    public function delete(string $tokenHash): void
    {
        $this->database->execute('DELETE FROM sessions WHERE token_hash = :token_hash', ['token_hash' => $tokenHash]);
    }

    public function deleteForUser(int $userId): void
    {
        $this->database->execute('DELETE FROM sessions WHERE user_id = :user_id', ['user_id' => $userId]);
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        return $this->database->execute('DELETE FROM sessions WHERE expires_at <= :now', ['now' => UtcDateTime::format($now)]);
    }
}
