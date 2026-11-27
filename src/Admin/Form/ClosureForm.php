<?php

declare(strict_types=1);

namespace FourStreet\Admin\Form;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;

/**
 * Il modulo "Chiudi la palestra" delle chiusure: il primo giorno (obbligatorio), l'ultimo (facoltativo: vuoto, un giorno
 * solo) e la nota per il titolare. Le date le manda il campo data del browser (AAAA-MM-GG); chi le scrive a mano, in un
 * browser senza calendario, le scrive come si scrivono in Italia (24/12/2026): vanno bene tutte e due. Qui si controlla
 * come sono scritti i campi e che l'intervallo sia di al più ScheduleChange::MAX_DAYS giorni, e si dice sul campo cosa
 * correggere; se il giorno si può chiudere (non è passato, non è prima dell'inaugurazione…) lo dice ScheduleEditor.
 * I valori scritti tornano nel modulo se c'è un errore.
 */
final readonly class ClosureForm
{
    public const string FROM = ChangeForm::FROM;
    public const string TO = ChangeForm::TO;
    public const string NOTE = ChangeForm::NOTE;

    /**
     * I valori sono come li ha scritti il titolare, per ricompilare il modulo.
     *
     * @param ScheduleChange|null $change la chiusura, se il modulo è valido
     */
    private function __construct(
        public string $from,
        public string $to,
        public string $note,
        public FormErrors $errors,
        private ?ScheduleChange $change,
    ) {}

    public static function blank(): self
    {
        return new self('', '', '', new FormErrors(), null);
    }

    public static function fromRequest(Request $request): self
    {
        $from = $request->bodyParam(self::FROM) ?? '';
        $to = $request->bodyParam(self::TO) ?? '';
        $note = $request->bodyParam(self::NOTE) ?? '';

        $errors = new FormErrors();
        $first = self::date($from);
        if ($first === null) {
            $errors = $errors->with(self::FROM, 'Scegli il primo giorno di chiusura.');
        } elseif (is_string($first)) {
            $errors = $errors->with(self::FROM, $first);
        }
        $last = self::date($to);
        if (is_string($last)) {
            $errors = $errors->with(self::TO, $last);
        } elseif ($first instanceof LocalDate && $last !== null) {
            if ($last->isBefore($first)) {
                $errors = $errors->with(self::TO, 'L\'ultimo giorno viene prima del primo: controlla le date.');
            } elseif ($first->addDays(ScheduleChange::MAX_DAYS - 1)->isBefore($last)) {
                $errors = $errors->with(self::TO, sprintf('Al massimo %d giorni per volta: per un periodo più lungo, chiudi in due volte.', ScheduleChange::MAX_DAYS));
            }
        }
        $closureNote = ClosureNote::parse($note);
        if ($closureNote->error !== null) {
            $errors = $errors->with(self::NOTE, $closureNote->error);
        }

        $change = $errors->isEmpty() && $first instanceof LocalDate && !is_string($last)
            ? ScheduleChange::close($first, $last ?? $first, $closureNote->value)
            : null;

        return new self($from, $to, $note, $errors, $change);
    }

    /** Lo stesso modulo, con un errore in più: il perché dell'editor, sul campo del primo giorno. */
    public function withError(string $field, string $message): self
    {
        return new self($this->from, $this->to, $this->note, $this->errors->with($field, $message), $this->change);
    }

    public function isValid(): bool
    {
        return $this->errors->isEmpty();
    }

    /** La chiusura chiesta: solo per un modulo valido. */
    public function change(): ScheduleChange
    {
        return $this->change ?? throw new LogicException('Modulo della chiusura non valido.');
    }

    /**
     * Il valore da rimettere in un campo data: il giorno come lo vuole il campo (AAAA-MM-GG), se si legge; altrimenti
     * com'è arrivato (un campo data lo mostra vuoto, e l'errore dice cosa scrivere).
     */
    public static function refill(string $input): string
    {
        $date = self::date($input);

        return $date instanceof LocalDate ? $date->value : $input;
    }

    /**
     * Un giorno scritto nel campo: AAAA-MM-GG oppure GG/MM/AAAA (anche 1/2/2027), gli spazi ai lati non contano.
     * Null se il campo è vuoto; il messaggio d'errore se è scritto male o se quel giorno non esiste (31 aprile).
     */
    private static function date(string $input): LocalDate|string|null
    {
        $text = trim($input);
        if ($text === '') {
            return null;
        }
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $text, $parts) === 1) {
            [, $year, $month, $day] = $parts;
        } elseif (preg_match('#\A(\d{1,2})/(\d{1,2})/(\d{4})\z#', $text, $parts) === 1) {
            [, $day, $month, $year] = $parts;
        } else {
            return 'Scrivi la data come 24/12/2026.';
        }

        return LocalDate::tryFrom(sprintf('%s-%02d-%02d', $year, (int) $month, (int) $day)) ?? 'Questo giorno non esiste: controlla la data.';
    }
}
