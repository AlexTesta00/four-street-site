<?php

declare(strict_types=1);

namespace FourStreet\Config;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Configurazione del sito (config/site.php), validata e tipizzata.
 * Un valore sbagliato ferma il sito con un messaggio chiaro invece di produrre pagine sbagliate.
 */
final readonly class SiteConfig
{
    private const string OPENING_FORMAT = 'Y-m-d H:i';

    private const array KEYS = [
        'name',
        'timezone',
        'opening',
        'opening_time_confirmed',
        'address',
        'maps_url',
        'links',
        'site_url',
    ];

    /**
     * @param array<string, non-empty-string> $links solo i link pubblicati (quelli vuoti sono esclusi)
     */
    private function __construct(
        public string $name,
        public DateTimeZone $timezone,
        public DateTimeImmutable $opening,
        public bool $openingTimeConfirmed,
        public string $address,
        public ?string $mapsUrl,
        public array $links,
        public ?string $siteUrl,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidSiteConfig(sprintf('File di configurazione non trovato: %s', $path));
        }

        $values = require $path;
        if (!is_array($values)) {
            throw new InvalidSiteConfig(sprintf('Il file di configurazione %s deve restituire un array.', $path));
        }

        return self::fromArray($values);
    }

    /**
     * @param array<mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $unknown = array_diff(array_map(strval(...), array_keys($values)), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidSiteConfig(sprintf(
                'Configurazione del sito non valida: chiavi sconosciute (%s). Valori ammessi: %s.',
                implode(', ', $unknown),
                implode(', ', self::KEYS),
            ));
        }

        $timezone = self::timezone($values);
        $siteUrl = self::optionalUrl($values, 'site_url');

        return new self(
            name: self::text($values, 'name'),
            timezone: $timezone,
            opening: self::opening($values, $timezone),
            openingTimeConfirmed: self::flag($values, 'opening_time_confirmed'),
            address: self::text($values, 'address'),
            mapsUrl: self::optionalUrl($values, 'maps_url'),
            links: self::links($values),
            siteUrl: $siteUrl === null ? null : rtrim($siteUrl, '/'),
        );
    }

    /**
     * Link pubblicato con quel nome (instagram, whatsapp…), oppure null se manca o è vuoto.
     */
    public function link(string $name): ?string
    {
        return $this->links[$name] ?? null;
    }

    /**
     * Orario dell'inaugurazione ("15:00"), solo se confermato.
     */
    public function openingTime(): ?string
    {
        return $this->openingTimeConfirmed ? $this->opening->format('H:i') : null;
    }

    /**
     * @param array<mixed> $values
     */
    private static function text(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw self::invalid($key, 'deve essere un testo non vuoto', $value);
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw self::invalid($key, 'deve stare su una riga, senza caratteri di controllo', $value);
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     */
    private static function timezone(array $values): DateTimeZone
    {
        $value = $values['timezone'] ?? null;
        if (!is_string($value) || !in_array($value, DateTimeZone::listIdentifiers(), true)) {
            throw self::invalid('timezone', 'deve essere un fuso orario come "Europe/Rome"', $value);
        }

        return new DateTimeZone($value);
    }

    /**
     * @param array<mixed> $values
     */
    private static function opening(array $values, DateTimeZone $timezone): DateTimeImmutable
    {
        $value = $values['opening'] ?? null;
        if (is_string($value)) {
            $opening = DateTimeImmutable::createFromFormat('!' . self::OPENING_FORMAT, $value, $timezone);
            // Il confronto scarta le date inesistenti (31 aprile, ore saltate dal cambio dell'ora).
            if ($opening !== false && $opening->format(self::OPENING_FORMAT) === $value) {
                return $opening;
            }
        }

        throw self::invalid('opening', 'deve essere una data e ora esistenti nel formato AAAA-MM-GG HH:MM', $value);
    }

    /**
     * @param array<mixed> $values
     */
    private static function flag(array $values, string $key): bool
    {
        $value = $values[$key] ?? null;
        if (!is_bool($value)) {
            throw self::invalid($key, 'deve essere true oppure false', $value);
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     */
    private static function optionalUrl(array $values, string $key): ?string
    {
        $value = $values[$key] ?? '';
        if ($value === '') {
            return null;
        }
        if (!is_string($value) || !self::isWebUrl($value)) {
            throw self::invalid($key, 'deve essere vuoto oppure un indirizzo completo http(s)://…', $value);
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, non-empty-string>
     */
    private static function links(array $values): array
    {
        $value = $values['links'] ?? [];
        if (!is_array($value)) {
            throw self::invalid('links', 'deve essere un elenco nome => indirizzo', $value);
        }

        $links = [];
        foreach ($value as $name => $url) {
            if (!is_string($name) || $name === '') {
                throw self::invalid('links', 'deve avere un nome per ogni link', $name);
            }
            // I link vuoti non vengono pubblicati.
            if ($url === '') {
                continue;
            }
            if (!is_string($url) || !self::isWebUrl($url)) {
                throw self::invalid('links.' . $name, 'deve essere vuoto oppure un indirizzo completo http(s)://…', $url);
            }
            $links[$name] = $url;
        }

        return $links;
    }

    private static function isWebUrl(string $value): bool
    {
        if (preg_match('/[\s\x00-\x1F\x7F]/', $value) === 1) {
            return false;
        }
        $parts = parse_url($value);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== '';
    }

    private static function invalid(string $key, string $rule, mixed $found): InvalidSiteConfig
    {
        $shown = is_string($found) ? '"' . $found . '"' : get_debug_type($found);

        return new InvalidSiteConfig(sprintf(
            'Configurazione del sito non valida: "%s" %s (trovato: %s).',
            $key,
            $rule,
            $shown,
        ));
    }
}
