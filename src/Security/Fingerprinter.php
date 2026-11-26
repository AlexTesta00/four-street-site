<?php

declare(strict_types=1);

namespace FourStreet\Security;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Impronte con chiave (HMAC-SHA256) di dati personali che servono solo per riconoscerli, mai per leggerli:
 * gli indirizzi IP e le email dei contatori dei tentativi. Senza la chiave segreta di config/local.php
 * un'impronta non si può ricondurre al dato, nemmeno provando tutti gli indirizzi IPv4.
 */
final readonly class Fingerprinter
{
    public const int MIN_SECRET_LENGTH = 32;

    public function __construct(
        #[SensitiveParameter]
        private string $secret,
    ) {
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new InvalidArgumentException(sprintf('La chiave segreta deve avere almeno %d caratteri.', self::MIN_SECRET_LENGTH));
        }
    }

    /**
     * @param string $purpose a cosa serve l'impronta: la stessa email ha impronte diverse per scopi diversi
     */
    public function fingerprint(string $purpose, #[SensitiveParameter] string $value): string
    {
        return hash_hmac('sha256', $purpose . "\0" . $value, $this->secret);
    }
}
