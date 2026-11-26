<?php

declare(strict_types=1);

namespace FourStreet\Account\Token;

use DateTimeImmutable;
use FourStreet\Database\Database;
use FourStreet\Database\UtcDateTime;
use UnexpectedValueException;

/**
 * I link monouso nella tabella account_tokens.
 */
final readonly class PdoAccountTokenRepository implements AccountTokenRepository
{
    public function __construct(
        private Database $database,
    ) {}

    public function add(AccountToken $token): void
    {
        $this->database->execute(
            'INSERT INTO account_tokens (token_hash, user_id, purpose, return_path, created_at, expires_at, used_at)'
            . ' VALUES (:token_hash, :user_id, :purpose, :return_path, :created_at, :expires_at, :used_at)',
            [
                'token_hash' => $token->tokenHash,
                'user_id' => $token->userId,
                'purpose' => $token->purpose->value,
                'return_path' => $token->returnPath,
                'created_at' => UtcDateTime::format($token->createdAt),
                'expires_at' => UtcDateTime::format($token->expiresAt),
                'used_at' => $token->usedAt === null ? null : UtcDateTime::format($token->usedAt),
            ],
        );
    }

    public function find(string $tokenHash, TokenPurpose $purpose): ?AccountToken
    {
        $row = $this->database->fetchOne(
            'SELECT token_hash, user_id, purpose, return_path, created_at, expires_at, used_at FROM account_tokens'
            . ' WHERE token_hash = :token_hash AND purpose = :purpose',
            ['token_hash' => $tokenHash, 'purpose' => $purpose->value],
        );
        if ($row === null) {
            return null;
        }

        $storedPurpose = $row->string('purpose');

        return new AccountToken(
            tokenHash: $row->string('token_hash'),
            userId: $row->int('user_id'),
            purpose: TokenPurpose::tryFrom($storedPurpose) ?? throw new UnexpectedValueException(sprintf('Scopo sconosciuto nel database: "%s".', $storedPurpose)),
            returnPath: $row->nullableString('return_path'),
            createdAt: $row->dateTime('created_at'),
            expiresAt: $row->dateTime('expires_at'),
            usedAt: $row->nullableDateTime('used_at'),
        );
    }

    public function consume(string $tokenHash, TokenPurpose $purpose, DateTimeImmutable $now): bool
    {
        return $this->database->execute(
            'UPDATE account_tokens SET used_at = :used_at'
            . ' WHERE token_hash = :token_hash AND purpose = :purpose AND used_at IS NULL AND expires_at > :now',
            [
                'used_at' => UtcDateTime::format($now),
                'token_hash' => $tokenHash,
                'purpose' => $purpose->value,
                'now' => UtcDateTime::format($now),
            ],
        ) === 1;
    }

    public function deleteUnused(int $userId, TokenPurpose $purpose): void
    {
        $this->database->execute(
            'DELETE FROM account_tokens WHERE user_id = :user_id AND purpose = :purpose AND used_at IS NULL',
            ['user_id' => $userId, 'purpose' => $purpose->value],
        );
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        return $this->database->execute('DELETE FROM account_tokens WHERE expires_at <= :now', ['now' => UtcDateTime::format($now)]);
    }
}
