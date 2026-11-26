<?php

declare(strict_types=1);

namespace FourStreet\Account\Token;

use FourStreet\Account\ReturnPath;
use FourStreet\Account\User;
use FourStreet\Clock\Clock;
use FourStreet\Security\Token;

/**
 * Crea i link monouso delle email. Un link nuovo cancella quelli non usati dello stesso tipo:
 * vale sempre e solo l'ultimo mandato.
 */
final readonly class AccountTokens
{
    public function __construct(
        private AccountTokenRepository $tokens,
        private Clock $clock,
    ) {}

    /**
     * @return Token il token da mettere nel link: nel database c'è solo la sua impronta
     */
    public function issue(User $user, TokenPurpose $purpose, ReturnPath $returnTo): Token
    {
        $this->tokens->deleteUnused($user->id, $purpose);

        $token = Token::generate();
        $now = $this->clock->now();
        $this->tokens->add(new AccountToken(
            tokenHash: $token->hash(),
            userId: $user->id,
            purpose: $purpose,
            returnPath: $returnTo->isDefault() ? null : $returnTo->value,
            createdAt: $now,
            expiresAt: $now->modify(sprintf('+%d seconds', $purpose->lifetimeSeconds())),
        ));

        return $token;
    }
}
