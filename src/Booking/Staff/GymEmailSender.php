<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use DateTimeImmutable;
use FourStreet\Account\UserRepository;
use FourStreet\Booking\Booking;
use FourStreet\Booking\BookingEmails;
use FourStreet\Booking\BookingNotifier;
use FourStreet\Booking\BookingRepository;
use FourStreet\Booking\EmailDelivery;
use FourStreet\Booking\GymEmail;
use FourStreet\Clock\Clock;
use LogicException;

/**
 * Manda l'email della palestra ai clienti registrati a cui la palestra ha disdetto una prenotazione, con lo stato nel
 * database (GymEmail), una alla volta: prepara l'email, prende la riga (BookingRepository::claimGymEmail(): da in
 * attesa o non partita a in invio, un passo solo), manda, e la segna partita o non partita. Così un'email partita non
 * si rimanda mai, e due invii insieme (due tocchi, due telefoni, una chiusura mentre si mandano le email rimaste) non
 * mandano mai due volte la stessa email: la riga la prende uno solo. Fra la presa e l'esito c'è soltanto l'invio (l'email
 * è già pronta): se il processo si interrompe proprio lì la riga resta in invio, non si sa se l'email è partita, e non
 * si rimanda (la pagina Disdette dice di avvisare il cliente).
 *
 * Gli invii non devono mai far scadere la pagina che li fa, e hanno due tetti:
 * - il tempo: al più SECONDS_PER_REQUEST secondi dal primo invio, sull'orologio (Clock); un invio cominciato finisce,
 *   poi ci si ferma, e le email rimaste restano com'erano (in attesa o non partite), da mandare dalla pagina Disdette;
 * - due invii falliti di fila: il server di posta non risponde, e ogni altro invio lo aspetterebbe fino al suo limite.
 * I tetti valgono per tutta la richiesta, non per una chiamata: per questo la classe tiene il tempo e i fallimenti (non è
 * readonly), e un'Application, che serve una richiesta sola, ne ha uno solo (AdminModule): una riapertura fatta a pezzi,
 * per esempio, non moltiplica il tempo.
 */
final class GymEmailSender
{
    /** Quanti secondi possono durare gli invii di una richiesta, dal primo. */
    public const int SECONDS_PER_REQUEST = 12;

    /** Dopo tanti invii falliti di fila le altre email non si provano: il server di posta non risponde. */
    public const int FAILURES_BEFORE_GIVING_UP = 2;

    /** Fino a quando si comincia un invio nuovo: il primo invio della richiesta più SECONDS_PER_REQUEST. */
    private ?DateTimeImmutable $deadline = null;

    private int $failuresInARow = 0;

    /**
     * @param BookingEmails $messages prepara l'email della disdetta fatta dalla palestra
     * @param BookingNotifier $notifier la manda (un invio fallito finisce nel log)
     */
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly UserRepository $users,
        private readonly BookingEmails $messages,
        private readonly BookingNotifier $notifier,
        private readonly Clock $clock,
    ) {}

    /**
     * Manda le email, nell'ordine, finché i tetti lo permettono.
     *
     * @param list<array{Booking, string|null}> $emails le disdette della palestra di clienti registrati, ognuna con il
     *                                                  perché in una frase per l'email, oppure null
     */
    public function send(array $emails): GymEmailRound
    {
        $results = [];
        foreach ($emails as [$booking, $reason]) {
            $stopped = $this->stop();
            if ($stopped !== null) {
                return new GymEmailRound($results, $stopped);
            }
            $result = $this->sendOne($booking, $reason);
            if ($result !== null) {
                $results[$booking->id] = $result;
            }
        }

        return new GymEmailRound($results);
    }

    /** Perché in questa richiesta non si comincia più nessun invio, se è così. */
    private function stop(): ?GymEmailStop
    {
        return match (true) {
            $this->failuresInARow >= self::FAILURES_BEFORE_GIVING_UP => GymEmailStop::MailDown,
            $this->deadline !== null && $this->clock->now() >= $this->deadline => GymEmailStop::TimeUp,
            default => null,
        };
    }

    /** Com'è andata l'email di quella disdetta; null se non c'era nessuno a cui mandarla (la riga resta com'era). */
    private function sendOne(Booking $booking, ?string $reason): ?GymEmail
    {
        $userId = $booking->userId ?? throw new LogicException(sprintf('La prenotazione %d non ha l\'account: nessuna email da mandare.', $booking->id));
        $customer = $this->users->findById($userId);
        // Un account cancellato si porta via anche le sue prenotazioni: nessuno da avvisare, e nessun invio fallito.
        if ($customer === null) {
            return null;
        }
        // L'email si prepara prima di prendere la riga: un errore nel prepararla la lascia com'era, da rimandare.
        $message = $this->messages->cancellationByGym($customer, $booking, $reason);

        // L'ha presa un altro invio nello stesso istante (o è già partita): la manda lui.
        if (!$this->bookings->claimGymEmail($booking->id)) {
            return GymEmail::Sending;
        }
        // Il tempo comincia dal primo invio, ed è tempo reale (il cambio dell'ora non conta).
        $now = $this->clock->now();
        $this->deadline ??= $now->setTimestamp($now->getTimestamp() + self::SECONDS_PER_REQUEST);

        $result = $this->notifier->deliver($message) === EmailDelivery::Sent ? GymEmail::Sent : GymEmail::Failed;
        $this->bookings->settleGymEmail($booking->id, $result);
        $this->failuresInARow = $result === GymEmail::Failed ? $this->failuresInARow + 1 : 0;

        return $result;
    }
}
