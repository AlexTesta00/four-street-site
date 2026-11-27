<?php

declare(strict_types=1);

namespace FourStreet\Admin\Form;

use FourStreet\Booking\BookingRules;
use FourStreet\Booking\Schedule\RulesChange;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;

/**
 * Il modulo delle impostazioni del pannello: le regole delle prenotazioni che il titolare cambia (RulesChange).
 * Le prenotazioni dal sito accese o spente, quanti giorni in anticipo si prenota, fino a quando si prenota e si disdice
 * (una delle scelte dell'elenco) e quante prenotazioni future può avere un cliente. Qui si controlla come sono scritti
 * i valori, con gli stessi limiti di RulesChange, e si dice sul campo cosa correggere; i valori scritti tornano nel
 * modulo se c'è un errore.
 *
 * L'interruttore delle prenotazioni dal sito c'è solo se è acceso anche booking.online di config/site.php: con il file
 * spento il campo non si mostra e, se arriva lo stesso, non conta (vedi fromRequest()).
 */
final readonly class SettingsForm
{
    public const string ONLINE = 'prenotazioni';
    public const string DAYS_AHEAD = 'giorni-prenotabili';
    public const string BOOKING_CLOSES = 'chiusura-prenotazioni';
    public const string CANCEL_CLOSES = 'chiusura-disdette';
    public const string MAX_UPCOMING = 'prenotazioni-future';

    /** I valori delle due scelte delle prenotazioni dal sito. */
    public const string ON = 'accese';
    public const string OFF = 'spente';

    /**
     * Fino a quando si prenota o si disdice: le scelte dell'elenco, in minuti prima dell'inizio della fascia.
     * Il valore in vigore, se è un altro (scritto nel file), si aggiunge all'elenco (deadlines()).
     */
    public const array DEADLINES = [0, 15, 30, 60, 120, 180, 360, 720, 1440, 2880];

    /**
     * I valori sono come li ha scritti il titolare (o come li mostra il modulo nuovo), per ricompilarlo.
     *
     * @param string $online "accese", "spente", oppure ciò che è arrivato (vuoto, senza scelta)
     * @param RulesChange|null $rules le regole del modulo, se è valido
     */
    private function __construct(
        public string $online,
        public string $daysAhead,
        public string $bookingCloses,
        public string $cancelCloses,
        public string $maxUpcoming,
        public FormErrors $errors,
        private ?RulesChange $rules,
    ) {}

    /** Il modulo con le regole in vigore. */
    public static function of(RulesChange $rules): self
    {
        return new self(
            $rules->online === false ? self::OFF : self::ON,
            (string) $rules->daysAhead,
            (string) $rules->bookingClosesMinutesBefore,
            (string) $rules->cancelClosesMinutesBefore,
            (string) $rules->maxUpcoming,
            new FormErrors(),
            $rules,
        );
    }

    /**
     * Il modulo inviato. Le scelte di "fino a quando" valgono se sono fra quelle offerte: l'elenco, più il valore
     * in vigore ($current).
     *
     * Con l'interruttore del file spento ($switchable false) il campo delle prenotazioni dal sito non c'è, e quello
     * che arriva non conta: la scelta resta quella di prima (RulesChange con online null), così, quando chi gestisce
     * il sito le riaccende nel file, valgono come le aveva lasciate il titolare (accese, se non le ha mai spente).
     */
    public static function fromRequest(Request $request, RulesChange $current, bool $switchable): self
    {
        $online = $request->bodyParam(self::ONLINE) ?? '';
        $daysAhead = $request->bodyParam(self::DAYS_AHEAD) ?? '';
        $bookingCloses = $request->bodyParam(self::BOOKING_CLOSES) ?? '';
        $cancelCloses = $request->bodyParam(self::CANCEL_CLOSES) ?? '';
        $maxUpcoming = $request->bodyParam(self::MAX_UPCOMING) ?? '';

        $isOnline = match (true) {
            $online === self::ON => true,
            $online === self::OFF => false,
            default => null,
        };
        $missingChoice = $switchable && $isOnline === null;
        if (!$switchable) {
            $isOnline = null;
        }
        $days = self::number($daysAhead, 0, BookingRules::MAX_DAYS_AHEAD);
        $booking = self::deadline($bookingCloses, $current->bookingClosesMinutesBefore);
        $cancel = self::deadline($cancelCloses, $current->cancelClosesMinutesBefore);
        $max = self::number($maxUpcoming, 1, RulesChange::MAX_UPCOMING);

        $errors = new FormErrors();
        foreach ([
            self::ONLINE => $missingChoice ? 'Scegli se le prenotazioni dal sito sono accese o spente.' : null,
            self::DAYS_AHEAD => match ($days) {
                'empty' => sprintf('Scrivi fino a quanti giorni dopo oggi si prenota: un numero da 0 a %d.', BookingRules::MAX_DAYS_AHEAD),
                'text' => sprintf('Scrivi un numero intero da 0 a %d, senza lettere né virgole.', BookingRules::MAX_DAYS_AHEAD),
                'range' => sprintf('Si prenota al massimo %d giorni dopo oggi, circa due mesi: scrivi un numero da 0 a %d.', BookingRules::MAX_DAYS_AHEAD, BookingRules::MAX_DAYS_AHEAD),
                default => null,
            },
            self::BOOKING_CLOSES => $booking === null ? 'Scegli una delle voci dell\'elenco: fino a quando si prenota una fascia.' : null,
            self::CANCEL_CLOSES => $cancel === null ? 'Scegli una delle voci dell\'elenco: fino a quando si disdice una prenotazione.' : null,
            self::MAX_UPCOMING => match ($max) {
                'empty' => sprintf('Scrivi quante prenotazioni future può avere un cliente: un numero da 1 a %d.', RulesChange::MAX_UPCOMING),
                'text' => sprintf('Scrivi un numero intero da 1 a %d, senza lettere né virgole.', RulesChange::MAX_UPCOMING),
                'range' => sprintf('Almeno una e al massimo %d (una al giorno per circa due mesi): scrivi un numero da 1 a %d.', RulesChange::MAX_UPCOMING, RulesChange::MAX_UPCOMING),
                default => null,
            },
        ] as $field => $problem) {
            if ($problem !== null) {
                $errors = $errors->with($field, $problem);
            }
        }

        $rules = $errors->isEmpty() && is_int($days) && $booking !== null && $cancel !== null && is_int($max) && $max >= 1
            ? new RulesChange($isOnline, $days, $booking, $cancel, $max)
            : null;

        return new self($online, $daysAhead, $bookingCloses, $cancelCloses, $maxUpcoming, $errors, $rules);
    }

    public function isValid(): bool
    {
        return $this->errors->isEmpty();
    }

    /** Le regole da salvare: solo per un modulo valido. */
    public function rules(): RulesChange
    {
        return $this->rules ?? throw new LogicException('Modulo delle impostazioni non valido.');
    }

    /**
     * Le scelte di "fino a quando", in minuti prima dell'inizio: quelle dell'elenco più il valore in vigore, in ordine.
     *
     * @return list<int>
     */
    public static function deadlines(int $current): array
    {
        $minutes = self::DEADLINES;
        if (!in_array($current, $minutes, true)) {
            $minutes[] = $current;
            sort($minutes);
        }

        return $minutes;
    }

    /** Una scelta di "fino a quando", a parole: "Fino all'inizio della fascia", "Fino a un'ora prima", "Fino a 24 ore prima". */
    public static function deadlineLabel(int $minutes): string
    {
        return match (true) {
            $minutes === 0 => 'Fino all\'inizio della fascia',
            $minutes === 60 => 'Fino a un\'ora prima',
            $minutes % 60 === 0 => sprintf('Fino a %d ore prima', intdiv($minutes, 60)),
            $minutes === 1 => 'Fino a un minuto prima',
            default => sprintf('Fino a %d minuti prima', $minutes),
        };
    }

    /**
     * Un numero intero scritto nel campo, fra $min e $max (gli spazi ai lati non contano), oppure cosa non va:
     * il campo è vuoto, ha qualcosa che non è un numero intero, o il numero è fuori dai limiti.
     *
     * @return int|'empty'|'text'|'range'
     */
    private static function number(string $input, int $min, int $max): int|string
    {
        $text = trim($input);
        if ($text === '') {
            return 'empty';
        }
        if (preg_match('/\A\d+\z/', $text) !== 1) {
            return 'text';
        }
        // Senza gli zeri davanti, più di quattro cifre è comunque fuori dai limiti (e non sfora gli interi di PHP).
        $digits = ltrim($text, '0');
        $value = strlen($digits) > 4 ? PHP_INT_MAX : (int) $text;

        return $value < $min || $value > $max ? 'range' : $value;
    }

    /** I minuti di una scelta di "fino a quando", se è fra quelle offerte; null se il valore non lo è (modulo manomesso). */
    private static function deadline(string $input, int $current): ?int
    {
        foreach (self::deadlines($current) as $minutes) {
            if ($input === (string) $minutes) {
                return $minutes;
            }
        }

        return null;
    }
}
