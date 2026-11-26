<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Security\Password\PasswordHasher;
use SensitiveParameter;

/**
 * Le regole della password: da 8 a 128 caratteri, nessuna regola di composizione (contano la lunghezza
 * e il fatto di non riusarla). Con bcrypt anche al massimo 72 byte, perché il resto verrebbe ignorato.
 */
final readonly class PasswordPolicy
{
    public const int MIN_LENGTH = 8;
    public const int MAX_LENGTH = 128;

    /**
     * @param int|null $maxBytes byte che l'algoritmo considera, se ha un limite
     */
    public function __construct(
        private ?int $maxBytes = null,
    ) {}

    public static function for(PasswordHasher $hasher): self
    {
        return new self($hasher->maxBytes());
    }

    /** Il problema della password, detto alla persona, oppure null se va bene. */
    public function problem(#[SensitiveParameter] string $password): ?string
    {
        $length = mb_strlen($password);

        return match (true) {
            $password === '' => 'Scegli una password.',
            $length < self::MIN_LENGTH => sprintf('La password deve avere almeno %d caratteri.', self::MIN_LENGTH),
            $length > self::MAX_LENGTH => sprintf('La password può avere al massimo %d caratteri.', self::MAX_LENGTH),
            $this->maxBytes !== null && strlen($password) > $this->maxBytes => 'La password è troppo lunga: accorciala un po\'.',
            default => null,
        };
    }
}
