<?php

declare(strict_types=1);

namespace FourStreet\Account\Token;

use DateTimeImmutable;

/**
 * I link monouso delle email (conferma, nuova password), cercati per impronta.
 */
interface AccountTokenRepository
{
    public function add(AccountToken $token): void;

    public function find(string $tokenHash, TokenPurpose $purpose): ?AccountToken;

    /**
     * Segna il link come usato, se non lo era e non è scaduto. È atomico: di due richieste insieme
     * con lo stesso link ne vince una sola.
     *
     * @return bool true se il link è stato usato adesso, da questa chiamata
     */
    public function consume(string $tokenHash, TokenPurpose $purpose, DateTimeImmutable $now): bool;

    /** Cancella i link non usati dell'utente per quello scopo: valgono solo gli ultimi mandati. */
    public function deleteUnused(int $userId, TokenPurpose $purpose): void;

    /** Cancella i link scaduti, usati o no: restituisce quanti erano. */
    public function deleteExpired(DateTimeImmutable $now): int;
}
