<?php

declare(strict_types=1);

namespace FourStreet\Config;

use DateTimeImmutable;
use DateTimeZone;
use FourStreet\Booking\BookingRules;
use FourStreet\Content\PendingContent;

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
        'show_placeholders',
        'content',
        'booking',
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
        public PendingContent $content,
        public BookingRules $booking,
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
        $config = ConfigValues::root($values);
        $config->assertKnownKeys(self::KEYS);

        $timezone = self::timezone($config);
        $siteUrl = $config->optionalUrl('site_url');

        return new self(
            name: $config->text('name'),
            timezone: $timezone,
            opening: self::opening($config, $timezone),
            openingTimeConfirmed: $config->flag('opening_time_confirmed'),
            address: $config->text('address'),
            mapsUrl: $config->optionalUrl('maps_url'),
            links: self::links($config),
            siteUrl: $siteUrl === null ? null : rtrim($siteUrl, '/'),
            content: PendingContent::fromConfig($config->section('content'), $config->flag('show_placeholders')),
            booking: BookingRules::fromConfig($config->requiredSection('booking')),
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

    private static function timezone(ConfigValues $config): DateTimeZone
    {
        $value = $config->raw('timezone');
        if (!is_string($value) || !in_array($value, DateTimeZone::listIdentifiers(), true)) {
            throw $config->invalid('timezone', 'deve essere un fuso orario come "Europe/Rome"', $value);
        }

        return new DateTimeZone($value);
    }

    private static function opening(ConfigValues $config, DateTimeZone $timezone): DateTimeImmutable
    {
        $value = $config->raw('opening');
        if (is_string($value)) {
            $opening = DateTimeImmutable::createFromFormat('!' . self::OPENING_FORMAT, $value, $timezone);
            // Il confronto scarta le date inesistenti (31 aprile, ore saltate dal cambio dell'ora).
            if ($opening !== false && $opening->format(self::OPENING_FORMAT) === $value) {
                return $opening;
            }
        }

        throw $config->invalid('opening', 'deve essere una data e ora esistenti nel formato AAAA-MM-GG HH:MM', $value);
    }

    /**
     * @return array<string, non-empty-string>
     */
    private static function links(ConfigValues $config): array
    {
        $value = $config->raw('links') ?? [];
        if (!is_array($value)) {
            throw $config->invalid('links', 'deve essere un elenco nome => indirizzo', $value);
        }

        $links = [];
        foreach ($value as $name => $url) {
            if (!is_string($name) || $name === '') {
                throw $config->invalid('links', 'deve avere un nome per ogni link', $name);
            }
            // I link vuoti non vengono pubblicati.
            if ($url === '') {
                continue;
            }
            if (!is_string($url) || !ConfigValues::isWebUrl($url)) {
                throw $config->invalid('links.' . $name, 'deve essere vuoto oppure un indirizzo completo http(s)://…', $url);
            }
            $links[$name] = $url;
        }

        return $links;
    }
}
