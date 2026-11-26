<?php

declare(strict_types=1);

namespace FourStreet\Security\Csrf;

use FourStreet\Http\Request;
use LogicException;
use SensitiveParameter;

/**
 * Il token CSRF della richiesta, da mettere nel campo nascosto dei moduli. Lo aggiunge CsrfProtection.
 */
final readonly class CsrfToken
{
    public function __construct(
        #[SensitiveParameter]
        public string $value,
    ) {}

    public static function of(Request $request): self
    {
        return $request->context(self::class)
            ?? throw new LogicException('Nessun token CSRF nella richiesta: la rotta deve passare da CsrfProtection.');
    }
}
