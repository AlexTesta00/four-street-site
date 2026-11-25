<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use FourStreet\Asset\MtimeLookup;

/**
 * Date di modifica fisse, al posto del disco.
 */
final class InMemoryMtimeLookup implements MtimeLookup
{
    /** @var list<string> */
    private array $asked = [];

    /**
     * @param array<string, int> $mtimes percorso => timestamp
     */
    public function __construct(
        private readonly array $mtimes = [],
    ) {}

    public function mtime(string $file): ?int
    {
        $this->asked[] = $file;

        return $this->mtimes[$file] ?? null;
    }

    /**
     * @return list<string> i file richiesti, nell'ordine
     */
    public function asked(): array
    {
        return $this->asked;
    }
}
