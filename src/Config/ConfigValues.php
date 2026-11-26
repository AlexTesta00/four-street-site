<?php

declare(strict_types=1);

namespace FourStreet\Config;

/**
 * Lettura validata di un file di configurazione (config/site.php, config/local.php) o di una sua sezione
 * ("content", "database"…). Ogni errore nomina il file, la chiave completa ("booking.capacity")
 * e dice cosa correggere.
 */
final readonly class ConfigValues
{
    /**
     * @param array<mixed> $values
     * @param string $path chiave della sezione, vuota per la radice del file
     * @param string $source quale configurazione è: apre ogni messaggio d'errore
     */
    private function __construct(
        private array $values,
        private string $path,
        private string $source,
    ) {}

    /**
     * @param array<mixed> $values
     * @param string $source quale configurazione è, per esempio "Configurazione locale (config/local.php)"
     */
    public static function root(array $values, string $source = 'Configurazione del sito'): self
    {
        return new self($values, '', $source);
    }

    /**
     * Sezione annidata (un elenco chiave => valore). Se manca o è vuota, è una sezione vuota.
     */
    public function section(string $key): self
    {
        $value = $this->values[$key] ?? [];
        if (!is_array($value)) {
            throw $this->invalid($key, 'deve essere un elenco chiave => valore', $value);
        }

        return new self($value, $this->key($key), $this->source);
    }

    /**
     * Sezione che deve esserci: un elenco chiave => valore, anche vuoto.
     */
    public function requiredSection(string $key): self
    {
        $value = $this->values[$key] ?? null;
        if (!is_array($value)) {
            throw $this->invalid($key, 'deve essere un elenco chiave => valore', $value);
        }

        return $this->section($key);
    }

    /**
     * @param list<string> $allowed
     */
    public function assertKnownKeys(array $allowed): void
    {
        $unknown = array_diff(array_map(strval(...), array_keys($this->values)), $allowed);
        if ($unknown === []) {
            return;
        }

        throw new InvalidSiteConfig(sprintf(
            '%s non valida: chiavi sconosciute%s (%s). Valori ammessi: %s.',
            $this->source,
            $this->path === '' ? '' : sprintf(' in "%s"', $this->path),
            implode(', ', $unknown),
            implode(', ', $allowed),
        ));
    }

    /**
     * Valore così com'è, per le regole che valgono solo per quella chiave. Null se manca.
     */
    public function raw(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function text(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw $this->invalid($key, 'deve essere un testo non vuoto', $value);
        }

        return $this->singleLine($key, $value);
    }

    /**
     * Testo facoltativo: vuoto o mancante vale null.
     */
    public function optionalText(string $key): ?string
    {
        $value = $this->values[$key] ?? '';
        if (!is_string($value)) {
            throw $this->invalid($key, 'deve essere un testo, oppure vuoto', $value);
        }
        if (trim($value) === '') {
            return null;
        }

        return $this->singleLine($key, $value);
    }

    public function flag(string $key): bool
    {
        $value = $this->values[$key] ?? null;
        if (!is_bool($value)) {
            throw $this->invalid($key, 'deve essere true oppure false', $value);
        }

        return $value;
    }

    /**
     * @return positive-int
     */
    public function positiveInt(string $key): int
    {
        $value = $this->values[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw $this->invalid($key, 'deve essere un numero intero maggiore di zero', is_int($value) ? (string) $value : $value);
        }

        return $value;
    }

    /**
     * Numero intero maggiore di zero che può mancare: se manca vale $default.
     *
     * @param positive-int $default
     *
     * @return positive-int
     */
    public function optionalPositiveInt(string $key, int $default): int
    {
        return array_key_exists($key, $this->values) ? $this->positiveInt($key) : $default;
    }

    public function optionalUrl(string $key): ?string
    {
        $value = $this->values[$key] ?? '';
        if ($value === '') {
            return null;
        }
        if (!is_string($value) || !self::isWebUrl($value)) {
            throw $this->invalid($key, 'deve essere vuoto oppure un indirizzo completo http(s)://…', $value);
        }

        return $value;
    }

    /**
     * Testo segreto (password, chiavi), facoltativo: si prende com'è, spazi compresi; vuoto o mancante vale null.
     * Negli errori il valore non compare mai.
     */
    public function optionalSecret(string $key): ?string
    {
        $value = $this->values[$key] ?? '';
        if (!is_string($value)) {
            throw $this->invalid($key, 'deve essere un testo, oppure vuoto', $value);
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw $this->invalid($key, 'deve stare su una riga, senza caratteri di controllo', $value, hidden: true);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Errore su una chiave di questa sezione, con il percorso completo nel messaggio.
     *
     * @param bool $hidden il valore è un segreto: il messaggio ne dice solo la lunghezza
     */
    public function invalid(string $key, string $rule, mixed $found, bool $hidden = false): InvalidSiteConfig
    {
        $shown = match (true) {
            is_string($found) && $hidden => sprintf('un testo di %d caratteri', mb_strlen($found)),
            is_string($found) => '"' . $found . '"',
            default => get_debug_type($found),
        };

        return new InvalidSiteConfig(sprintf(
            '%s non valida: "%s" %s (trovato: %s).',
            $this->source,
            $this->key($key),
            $rule,
            $shown,
        ));
    }

    public static function isWebUrl(string $value): bool
    {
        if (preg_match('/[\s\x00-\x1F\x7F]/', $value) === 1) {
            return false;
        }
        $parts = parse_url($value);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== '';
    }

    private function key(string $key): string
    {
        return $this->path === '' ? $key : $this->path . '.' . $key;
    }

    private function singleLine(string $key, string $value): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw $this->invalid($key, 'deve stare su una riga, senza caratteri di controllo', $value);
        }

        return $value;
    }
}
