<?php

declare(strict_types=1);

namespace FourStreet\Http\Form;

/**
 * Gli errori di un modulo inviato: un messaggio per campo, nell'ordine dei campi della pagina.
 * Il riepilogo in cima al modulo li elenca con un link al campo; ogni campo mostra il suo.
 */
final readonly class FormErrors
{
    /**
     * @param array<string, string> $messages nome del campo => messaggio
     */
    public function __construct(
        private array $messages = [],
    ) {}

    /** Gli stessi errori più uno: il primo errore di un campo resta quello mostrato. */
    public function with(string $field, string $message): self
    {
        return isset($this->messages[$field]) ? $this : new self([...$this->messages, $field => $message]);
    }

    public function has(string $field): bool
    {
        return isset($this->messages[$field]);
    }

    public function get(string $field): ?string
    {
        return $this->messages[$field] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->messages;
    }
}
