<?php

declare(strict_types=1);

namespace FourStreet\Admin\Form;

use FourStreet\Booking\Schedule\ScheduleChange;

/**
 * La nota di una chiusura scritta dal titolare: facoltativa, a uso interno ("ferie"), mai per i clienti, mai in un
 * indirizzo. Si salva come la vuole ScheduleChange::normalizeNote() (spazi in ordine, al più 100 caratteri, niente
 * caratteri di controllo): qui si dice perché una nota non va, troppo lunga oppure con caratteri che non si salvano.
 */
final readonly class ClosureNote
{
    /**
     * @param string|null $value la nota da salvare; null se il campo è vuoto (o se c'è un errore)
     * @param string|null $error che cosa correggere
     */
    private function __construct(
        public ?string $value,
        public ?string $error,
    ) {}

    public static function parse(string $input): self
    {
        $note = ScheduleChange::normalizeNote($input);
        if ($note !== null) {
            return new self($note, null);
        }

        // Testo che non è UTF-8 valido (un modulo manomesso o un copia e incolla rovinato): non si salva.
        if (preg_match('//u', $input) !== 1) {
            return self::invalid();
        }
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $input));
        if ($collapsed === '') {
            return new self(null, null);
        }
        if (mb_strlen($collapsed) > ScheduleChange::NOTE_MAX_LENGTH) {
            return new self(null, sprintf('La nota può avere al massimo %d caratteri: accorciala.', ScheduleChange::NOTE_MAX_LENGTH));
        }

        return self::invalid();
    }

    private static function invalid(): self
    {
        return new self(null, 'La nota ha dei caratteri che non si salvano: scrivila con lettere, numeri e punteggiatura.');
    }
}
