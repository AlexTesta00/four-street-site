<?php

declare(strict_types=1);

namespace FourStreet\Security\Password;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Le funzioni password_* di PHP: Argon2id quando PHP lo offre, altrimenti bcrypt.
 */
final readonly class NativePasswordHasher implements PasswordHasher
{
    private const string ARGON2ID = 'argon2id';
    private const string BCRYPT = '2y';

    /** bcrypt considera solo i primi 72 byte: oltre, due password diverse avrebbero lo stesso hash. */
    private const int BCRYPT_MAX_BYTES = 72;

    /**
     * @param array<string, int> $options
     */
    private function __construct(
        private string $algorithm,
        private array $options,
    ) {}

    /** Argon2id con i parametri predefiniti di PHP (64 MiB, 4 passate), oppure bcrypt con costo 12. */
    public static function recommended(): self
    {
        return self::argon2idAvailable() ? self::argon2id() : self::bcrypt();
    }

    public static function argon2id(int $memoryCost = 65_536, int $timeCost = 4, int $threads = 1): self
    {
        if (!self::argon2idAvailable()) {
            throw new InvalidArgumentException('Questo PHP non ha Argon2id: usa bcrypt.');
        }

        return new self(self::ARGON2ID, ['memory_cost' => $memoryCost, 'time_cost' => $timeCost, 'threads' => $threads]);
    }

    public static function bcrypt(int $cost = 12): self
    {
        if ($cost < 4 || $cost > 31) {
            throw new InvalidArgumentException(sprintf('Costo di bcrypt fuori dai limiti (4-31): %d.', $cost));
        }

        return new self(self::BCRYPT, ['cost' => $cost]);
    }

    public static function argon2idAvailable(): bool
    {
        return in_array(self::ARGON2ID, password_algos(), true);
    }

    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, $this->algorithm, $this->options);
    }

    public function verify(#[SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }

    public function dummyHash(): string
    {
        // Sale e hash fissi, parametri di oggi: password_verify fa tutto il calcolo e poi risponde no.
        return $this->algorithm === self::ARGON2ID
            ? sprintf(
                '$argon2id$v=19$m=%d,t=%d,p=%d$%s$%s',
                $this->options['memory_cost'] ?? 65_536,
                $this->options['time_cost'] ?? 4,
                $this->options['threads'] ?? 1,
                'Zm91cnN0cmVldGR1bW15cw',
                'ZHVtbXloYXNoZHVtbXloYXNoZHVtbXloYXNoZHVtbXk',
            )
            : sprintf('$2y$%02d$%s', $this->options['cost'] ?? 12, 'Zm91cnN0cmVldGR1bW15c.ZHVtbXloYXNoZHVtbXloYXNoZHVtbXk');
    }

    public function maxBytes(): ?int
    {
        return $this->algorithm === self::BCRYPT ? self::BCRYPT_MAX_BYTES : null;
    }
}
