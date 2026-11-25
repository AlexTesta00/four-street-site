<?php

declare(strict_types=1);

namespace FourStreet\View;

use FourStreet\Asset\AssetUrls;
use InvalidArgumentException;
use Throwable;

/**
 * Esegue i template PHP di templates/: pagine, layout e parti riutilizzabili.
 * Un template vede soltanto i dati che riceve e gli aiuti di $view.
 */
final readonly class View
{
    private const string NAME_PATTERN = '#\A[a-z0-9_-]+(?:/[a-z0-9_-]+)*\z#';
    private const string VARIABLE_PATTERN = '/\A[a-zA-Z_][a-zA-Z0-9_]*\z/';
    private const array RESERVED_VARIABLES = ['view', 'this', '__template', '__variables'];

    public function __construct(
        private string $directory,
        private AssetUrls $assets,
        private Icons $icons,
    ) {}

    /**
     * @param string $name nome del template senza estensione, per esempio "pages/home"
     * @param array<string, mixed> $data variabili del template
     */
    public function render(string $name, array $data = []): Html
    {
        $template = new Template($this, $this->assets, $this->icons);
        $html = $this->evaluate($this->file($name), $data, $template);

        $layout = $template->takeLayout();
        if ($layout === null) {
            return $html;
        }

        return $this->render($layout['name'], ['content' => $html] + $layout['data']);
    }

    private function file(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Nome di template non valido: "%s".', $name));
        }
        $file = $this->directory . '/' . $name . '.php';
        if (!is_file($file)) {
            throw new TemplateNotFound(sprintf('Template non trovato: %s', $file));
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function evaluate(string $file, array $data, Template $template): Html
    {
        foreach (array_keys($data) as $variable) {
            if (preg_match(self::VARIABLE_PATTERN, $variable) !== 1 || in_array($variable, self::RESERVED_VARIABLES, true)) {
                throw new InvalidArgumentException(sprintf('Nome di variabile non valido per un template: "%s".', $variable));
            }
        }

        $level = ob_get_level();
        ob_start();
        try {
            // Funzione statica: nel template esistono solo $view e i dati ($__template è il file stesso).
            (static function (string $__template, array $__variables): void {
                extract($__variables);
                unset($__variables);

                require $__template;
            })($file, ['view' => $template] + $data);
        } catch (Throwable $error) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $error;
        }

        return new Html((string) ob_get_clean());
    }
}
