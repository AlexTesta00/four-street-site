<?php

declare(strict_types=1);

namespace FourStreet\Seo;

use FourStreet\Config\SiteConfig;
use FourStreet\View\Html;

/**
 * Dati strutturati della palestra per i motori di ricerca (schema.org ExerciseGym), in JSON-LD.
 * Contiene solo fatti certi: nome, indirizzo del sito, indirizzo della palestra, profilo Instagram.
 */
final readonly class GymStructuredData
{
    /** Dentro <script> il JSON non deve poter chiudere il tag né uscire dalle virgolette: <, >, &, ' e " diventano \u…. */
    private const int JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * @param string|null $url pagina principale del sito, se l'indirizzo pubblico è configurato
     * @param list<string> $sameAs profili ufficiali della palestra su altri siti
     */
    public function __construct(
        private string $name,
        private ?string $url,
        private string $address,
        private array $sameAs = [],
    ) {}

    public static function fromSite(SiteConfig $site): self
    {
        $instagram = $site->link('instagram');

        return new self(
            $site->name,
            $site->siteUrl === null ? null : $site->siteUrl . '/',
            $site->address,
            $instagram === null ? [] : [$instagram],
        );
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toArray(): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'ExerciseGym',
            'name' => $this->name,
        ];
        if ($this->url !== null) {
            $data['url'] = $this->url;
        }
        $data['address'] = $this->address;
        if ($this->sameAs !== []) {
            $data['sameAs'] = $this->sameAs;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), self::JSON_FLAGS);
    }

    /**
     * Il blocco <script type="application/ld+json"> per la testata: è un blocco di dati, non uno script
     * da eseguire, quindi la Content-Security-Policy non lo riguarda.
     */
    public function script(): Html
    {
        return new Html('<script type="application/ld+json">' . $this->toJson() . '</script>');
    }
}
