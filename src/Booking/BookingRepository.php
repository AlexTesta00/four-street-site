<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use FourStreet\Account\FullName;

/**
 * Le prenotazioni salvate. Le regole che devono valere anche con due richieste insieme (capienza,
 * una al giorno, al massimo N future, la stessa persona senza account una volta per fascia) le applicano
 * place() e placeGuest() in un passo solo, garantite dal database.
 */
interface BookingRepository
{
    /**
     * Prenota per il cliente il primo posto libero della fascia, se ha ancora diritto a prenotare:
     * nessuna prenotazione attiva quel giorno e meno di $maxUpcoming future. Le prenotazioni dello stesso
     * cliente passano una alla volta; se un altro prende il posto nello stesso istante, si riprova con il
     * successivo. Esiti: Booked, Full, AlreadyBooked, TooManyUpcoming.
     *
     * @param positive-int $capacity i posti della fascia
     * @param positive-int|null $maxUpcoming il massimo di prenotazioni future attive, oppure null senza limite
     * @param CreatedBy $createdBy chi la inserisce: il cliente stesso, oppure la palestra
     */
    public function place(int $userId, ScheduledSlot $slot, int $capacity, ?int $maxUpcoming, DateTimeImmutable $now, CreatedBy $createdBy): BookingResult;

    /**
     * Prenota il primo posto libero della fascia per una persona senza account, inserita dalla palestra.
     * Lo stesso nome nella stessa fascia è la stessa persona: c'è già (AlreadyBooked con quella prenotazione).
     * Le prenotazioni inserite dallo stesso titolare passano una alla volta, così lo stesso modulo inviato
     * due volte non scrive due righe. Esiti: Booked, Full, AlreadyBooked.
     *
     * @param positive-int $capacity i posti della fascia
     * @param int $staffId il titolare che la inserisce
     */
    public function placeGuest(FullName $guest, ScheduledSlot $slot, int $capacity, int $staffId, DateTimeImmutable $now): BookingResult;

    public function find(int $id): ?Booking;

    /** Una prenotazione con la sua persona (anche disdetta), per il pannello del titolare. */
    public function findEntry(int $id): ?RosterEntry;

    /** La prenotazione attiva del cliente in quel giorno, se c'è (al più una). */
    public function activeOn(int $userId, LocalDate $date): ?Booking;

    /**
     * Le prenotazioni attive del cliente da quel giorno in poi, dalla prima.
     *
     * @return list<Booking>
     */
    public function activeFrom(int $userId, LocalDate $from): array;

    /** Quante prenotazioni attive del cliente non sono ancora iniziate. */
    public function countUpcoming(int $userId, DateTimeImmutable $now): int;

    /** I posti presi delle fasce fra due giorni, compresi: una lettura sola. */
    public function occupancy(LocalDate $from, LocalDate $to): Occupancy;

    /**
     * Le prenotazioni attive di un giorno con le loro persone, per fascia e per posto: una lettura sola,
     * con i nomi e le email dei clienti nella stessa query.
     *
     * @return list<RosterEntry>
     */
    public function roster(LocalDate $date): array;

    /**
     * Disdice una prenotazione attiva: il posto si libera, la prenotazione resta come storico.
     *
     * @return bool false se non era attiva (già disdetta, o inesistente)
     */
    public function cancel(int $id, DateTimeImmutable $at, CancelledBy $by): bool;
}
