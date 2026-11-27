<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use FourStreet\Booking\LocalDate;
use FourStreet\Booking\SlotTime;
use FourStreet\Booking\Weekday;
use InvalidArgumentException;
use LogicException;

/**
 * Una modifica dell'orario, come la chiede il titolare dal pannello ("togli le 18:30 dal lunedì", "chiudi dal 24
 * al 26 dicembre"), non ancora controllata né fatta: ScheduleEditor la confronta con l'orario di adesso, dice se
 * si può e chi è prenotato dove sparisce una fascia, e la fa. È fatta solo di valori: la pagina di conferma la
 * rimanda tale e quale, e l'editor la ricontrolla da capo.
 */
final readonly class ScheduleChange
{
    /** Le chiusure e le riaperture coprono al più due mesi per volta: un intervallo più lungo è quasi sempre un errore. */
    public const int MAX_DAYS = 62;

    /** La nota di una chiusura, a uso interno: "ferie", "corso di aggiornamento". */
    public const int NOTE_MAX_LENGTH = 100;

    private function __construct(
        public ChangeKind $kind,
        private ?Weekday $weekday = null,
        private ?LocalDate $from = null,
        private ?LocalDate $to = null,
        private ?SlotTime $time = null,
        public ?string $note = null,
    ) {}

    public static function addWeekdayTime(Weekday $day, SlotTime $time): self
    {
        return new self(ChangeKind::AddWeekdayTime, weekday: $day, time: $time);
    }

    public static function removeWeekdayTime(Weekday $day, SlotTime $time): self
    {
        return new self(ChangeKind::RemoveWeekdayTime, weekday: $day, time: $time);
    }

    public static function addTime(LocalDate $date, SlotTime $time): self
    {
        return new self(ChangeKind::AddTime, from: $date, to: $date, time: $time);
    }

    public static function removeTime(LocalDate $date, SlotTime $time): self
    {
        return new self(ChangeKind::RemoveTime, from: $date, to: $date, time: $time);
    }

    /**
     * @param string|null $note la nota per il titolare, già passata da normalizeNote()
     */
    public static function close(LocalDate $from, LocalDate $to, ?string $note): self
    {
        self::assertRange($from, $to);
        if ($note !== null && $note !== self::normalizeNote($note)) {
            throw new InvalidArgumentException('La nota della chiusura va prima normalizzata.');
        }

        return new self(ChangeKind::Close, from: $from, to: $to, note: $note);
    }

    public static function reopen(LocalDate $from, LocalDate $to): self
    {
        self::assertRange($from, $to);

        return new self(ChangeKind::Reopen, from: $from, to: $to);
    }

    public static function backToWeek(LocalDate $date): self
    {
        return new self(ChangeKind::BackToWeek, from: $date, to: $date);
    }

    /** @param LocalDate $monday il lunedì della settimana da copiare nella successiva */
    public static function copyWeek(LocalDate $monday): self
    {
        if ($monday->weekday() !== Weekday::Monday) {
            throw new InvalidArgumentException(sprintf('Una settimana comincia di lunedì: %s.', $monday));
        }

        return new self(ChangeKind::CopyWeek, from: $monday, to: $monday->addDays(6));
    }

    /**
     * La nota come si salva: spazi in ordine, senza caratteri di controllo; null se vuota o non valida.
     * Chi la scrive (il modulo) dice se è troppo lunga o ha caratteri strani: qui si accetta solo una nota pulita.
     */
    public static function normalizeNote(string $note): ?string
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        if ($note === '' || preg_match('/[\x00-\x1F\x7F]/u', $note) === 1 || mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            return null;
        }

        return $note;
    }

    /** Il giorno della settimana (settimana tipo). */
    public function weekday(): Weekday
    {
        return $this->weekday ?? throw new LogicException(sprintf('La modifica %s non riguarda la settimana tipo.', $this->kind->name));
    }

    /** L'orario aggiunto o tolto. */
    public function time(): SlotTime
    {
        return $this->time ?? throw new LogicException(sprintf('La modifica %s non ha un orario.', $this->kind->name));
    }

    /** Il giorno (o il primo giorno) che cambia: per la copia, il lunedì della settimana copiata. */
    public function from(): LocalDate
    {
        return $this->from ?? throw new LogicException(sprintf('La modifica %s non riguarda un giorno.', $this->kind->name));
    }

    /** L'ultimo giorno: lo stesso di from() per un giorno solo; per la copia, la domenica della settimana copiata. */
    public function to(): LocalDate
    {
        return $this->to ?? throw new LogicException(sprintf('La modifica %s non riguarda un giorno.', $this->kind->name));
    }

    /** La modifica è della settimana tipo, non di giorni precisi. */
    public function isWeekly(): bool
    {
        return $this->kind === ChangeKind::AddWeekdayTime || $this->kind === ChangeKind::RemoveWeekdayTime;
    }

    /**
     * I giorni da from() a to(), compresi.
     *
     * @return list<LocalDate>
     */
    public function days(): array
    {
        $days = [];
        for ($day = $this->from(); !$day->isAfter($this->to()); $day = $day->addDays(1)) {
            $days[] = $day;
        }

        return $days;
    }

    private static function assertRange(LocalDate $from, LocalDate $to): void
    {
        if ($to->isBefore($from) || $from->addDays(self::MAX_DAYS - 1)->isBefore($to)) {
            throw new InvalidArgumentException(sprintf('Intervallo non valido: dal %s al %s (al più %d giorni).', $from, $to, self::MAX_DAYS));
        }
    }
}
