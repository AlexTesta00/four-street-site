<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

/**
 * Una cartella temporanea per un test, da cancellare con tutto il contenuto alla fine.
 * I percorsi usano sempre "/", anche su Windows.
 */
final class TemporaryDirectory
{
    public readonly string $path;

    public function __construct(string $prefix = '4street')
    {
        $this->path = str_replace('\\', '/', sys_get_temp_dir()) . '/' . $prefix . '-' . bin2hex(random_bytes(6));
        mkdir($this->path);
    }

    /** Una sottocartella nuova. */
    public function directory(string $name): string
    {
        $directory = $this->path . '/' . $name;
        mkdir($directory, 0o777, true);

        return $directory;
    }

    /** Un file con quel contenuto (le cartelle che mancano si creano). */
    public function file(string $name, string $content): string
    {
        $file = $this->path . '/' . $name;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0o777, true);
        }
        file_put_contents($file, $content);

        return $file;
    }

    /**
     * I file della cartella (non ricorsivo), in ordine di nome.
     *
     * @return list<string>
     */
    public function files(string $name = ''): array
    {
        $directory = $name === '' ? $this->path : $this->path . '/' . $name;
        $entries = is_dir($directory) ? scandir($directory) : false;

        return array_values(array_filter($entries === false ? [] : $entries, static fn(string $entry): bool => is_file($directory . '/' . $entry)));
    }

    public function remove(): void
    {
        self::delete($this->path);
    }

    private static function delete(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }
        $entries = is_dir($path) ? scandir($path) : false;
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::delete($path . '/' . $entry);
            }
        }
        if (is_dir($path)) {
            rmdir($path);
        }
    }
}
