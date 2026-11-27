<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use Closure;
use DateTimeImmutable;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;

/**
 * L'orario e le regole nel database, che il titolare cambia dal pannello: le letture di ScheduleSource più le scritture.
 * Le regole delle modifiche (il passato non si tocca, le fasce non si sovrappongono, le prenotazioni da disdire)
 * stanno in ScheduleEditor e RulesEditor: qui si scrive soltanto, dentro transaction().
 *
 * La prima modifica dell'orario copia nel database la settimana tipo e i closed_dates del file: da lì in poi
 * l'orario sta tutto nel database, e il file non conta più (resta la riserva della landing se il database non risponde).
 */
interface ScheduleStore extends ScheduleSource
{
    /** L'orario sta nel database: il titolare l'ha cambiato almeno una volta. Prima vale quello del file. */
    public function isInDatabase(): bool;

    /**
     * Gli orari della settimana tipo che valgono quel giorno, senza le eccezioni: per oggi possono essere diversi
     * da week(), se la settimana tipo è cambiata oggi (vale da domani).
     *
     * @return list<SlotTime>
     */
    public function weekTimesOn(LocalDate $date): array;

    /**
     * I giorni chiusi da quel giorno in poi, dal primo.
     *
     * @return list<DayPlan>
     */
    public function closuresFrom(LocalDate $from): array;

    /**
     * La prima istruzione di una transazione che cambia l'orario: da qui alla fine le altre modifiche dell'orario
     * aspettano, e le letture che seguono vedono l'orario lasciato da quella passata prima. Dimentica ciò che ha letto.
     */
    public function lock(): void;

    /**
     * Esegue $work in una transazione: le scritture qui sotto e le disdette delle prenotazioni (lo stesso database)
     * entrano tutte o nessuna.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function transaction(Closure $work): mixed;

    /**
     * Gli orari della settimana tipo per quel giorno della settimana, da $from in poi: i giorni prima restano com'erano.
     *
     * @param list<SlotTime> $times in ordine, già controllati
     * @param int|null $by il titolare che la cambia
     */
    public function saveWeekday(Weekday $day, array $times, LocalDate $from, ?int $by, DateTimeImmutable $at): void;

    /**
     * Un orario suo per quel giorno, al posto della settimana tipo: chiuso, oppure con quelle fasce.
     *
     * @param list<SlotTime> $times in ordine, già controllati; [] se chiuso
     * @param int|null $by il titolare che lo cambia
     */
    public function saveDay(LocalDate $date, bool $closed, array $times, ?string $note, ?int $by, DateTimeImmutable $at): void;

    /**
     * Il giorno torna alla settimana tipo.
     *
     * @param int|null $by il titolare che lo cambia
     */
    public function resetDay(LocalDate $date, ?int $by, DateTimeImmutable $at): void;

    /** Le regole del pannello, tutte insieme: da qui in poi valgono queste al posto di quelle del file. */
    public function saveRules(RulesChange $rules, ?int $by, DateTimeImmutable $at): void;

    /** Dimentica ciò che ha letto: la prossima lettura torna al database. Le scritture lo fanno da sole. */
    public function forget(): void;
}
