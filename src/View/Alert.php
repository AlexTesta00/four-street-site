<?php

declare(strict_types=1);

namespace FourStreet\View;

/**
 * Un avviso in pagina (partials/alert): conferma, errore o informazione.
 */
final readonly class Alert
{
    /**
     * @param 'success'|'error'|'info' $tone
     */
    public function __construct(
        public string $tone,
        public string $message,
        public ?string $title = null,
    ) {}

    public static function success(string $message, ?string $title = null): self
    {
        return new self('success', $message, $title);
    }

    public static function error(string $message, ?string $title = null): self
    {
        return new self('error', $message, $title);
    }

    public static function info(string $message, ?string $title = null): self
    {
        return new self('info', $message, $title);
    }

    /** Errori e conferme si annunciano subito ai lettori di schermo; le informazioni con calma. */
    public function role(): string
    {
        return $this->tone === 'error' ? 'alert' : 'status';
    }
}
