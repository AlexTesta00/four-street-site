<?php

declare(strict_types=1);

namespace FourStreet\View;

use FourStreet\Asset\AssetUrls;
use InvalidArgumentException;
use LogicException;

/**
 * Gli aiuti a disposizione di un template, come $view. Sono gli unici: niente variabili globali.
 * Ogni rendering ha la sua istanza, quindi il layout scelto da un template non si propaga agli altri.
 */
final class Template
{
    /** @var array{name: string, data: array<string, mixed>}|null */
    private ?array $layout = null;

    public function __construct(
        private readonly View $renderer,
        private readonly AssetUrls $assets,
        private readonly Icons $icons,
    ) {}

    /** Testo con escape, per il contenuto e per gli attributi. */
    public function e(Html|string|int|null $value): string
    {
        return Escaper::html($value);
    }

    /** URL versionato di un file statico, già pronto per un attributo. */
    public function asset(string $path): Html
    {
        return new Html(Escaper::html($this->assets->url($path)));
    }

    public function icon(string $name, string $class = ''): Html
    {
        return $this->icons->svg($name, $class);
    }

    /**
     * Attributi HTML da un elenco nome => valore, ognuno preceduto da uno spazio e con l'escape.
     * true scrive l'attributo senza valore (required), false e null lo tolgono.
     *
     * @param array<string, string|int|bool|null> $attributes
     */
    public function attributes(array $attributes): Html
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if (preg_match('/\A[a-z][a-z0-9-]*\z/', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('Nome di attributo non valido: "%s".', $name));
            }
            if ($value === null || $value === false) {
                continue;
            }
            $html .= ' ' . $name . ($value === true ? '' : '="' . Escaper::html($value) . '"');
        }

        return new Html($html);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function partial(string $name, array $data = []): Html
    {
        return $this->renderer->render($name, $data);
    }

    /**
     * Avvolge il template corrente in un layout, che riceve questi dati più $content.
     *
     * @param array<string, mixed> $data
     */
    public function layout(string $name, array $data = []): void
    {
        if ($this->layout !== null) {
            throw new LogicException(sprintf('Il layout è già impostato: "%s".', $this->layout['name']));
        }
        $this->layout = ['name' => $name, 'data' => $data];
    }

    /**
     * Il layout chiesto dal template appena eseguito (lo legge View, una volta sola).
     *
     * @internal
     *
     * @return array{name: string, data: array<string, mixed>}|null
     */
    public function takeLayout(): ?array
    {
        $layout = $this->layout;
        $this->layout = null;

        return $layout;
    }
}
