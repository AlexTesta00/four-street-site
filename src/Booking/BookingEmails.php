<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use DateTimeImmutable;
use FourStreet\Account\AccountUrls;
use FourStreet\Account\User;
use FourStreet\Calendar\IcsCalendar;
use FourStreet\Calendar\IcsEvent;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Origin;
use FourStreet\Mail\Attachment;
use FourStreet\Mail\MailTemplates;
use FourStreet\Mail\Message;
use FourStreet\Text\ItalianDateFormatter;

/**
 * Le email delle prenotazioni: la conferma, con l'evento da aggiungere al calendario (la stessa anche quando
 * la prenotazione la inserisce la palestra), la disdetta del cliente e quella fatta dalla palestra.
 * I link sono assoluti e nascono dall'origine pubblica del sito (base_url), mai dalla richiesta.
 */
final readonly class BookingEmails
{
    /** Il nome del file allegato e il suo tipo (METHOD:PUBLISH: un evento da aggiungere, non un invito). */
    public const string CALENDAR_FILE = 'prenotazione-4street.ics';
    public const string CALENDAR_TYPE = 'text/calendar; charset=utf-8; method=PUBLISH';

    public function __construct(
        private MailTemplates $templates,
        private Origin $origin,
        private SiteConfig $site,
        private ItalianDateFormatter $dates,
        private BookingCalendar $calendar,
    ) {}

    /**
     * La conferma di una prenotazione appena fatta, con l'evento .ics. Dice fino a quando il cliente la disdice
     * dal suo account; se è già troppo tardi (la palestra l'ha inserita a ridosso dell'inizio, oltre la regola dei
     * clienti) dice di avvisare la palestra, invece di promettere una disdetta che non c'è.
     */
    public function confirmation(User $customer, Booking $booking, DateTimeImmutable $now): Message
    {
        $accountUrl = $this->origin->url(AccountUrls::HOME);
        $capacity = $this->calendar->rules()->capacity;
        $cancellation = $this->calendar->canCancel($booking->startsAt, $now) ? $this->calendar->rules()->cancellationDeadline() : null;

        return $this->templates->message(
            $customer->mailbox(),
            sprintf('Prenotazione confermata: %s, ore %s', $this->day($booking, withYear: false), $booking->time),
            'booking-confirmed',
            $this->data($customer, $booking, [
                'seat' => $booking->seat(),
                'capacity' => $capacity,
                'accountUrl' => $accountUrl,
                'cancellation' => $cancellation,
            ]),
        )->withAttachment(new Attachment(self::CALENDAR_FILE, self::CALENDAR_TYPE, $this->event($booking, $now, $accountUrl, $cancellation !== null)->render()));
    }

    /** L'avviso di una prenotazione disdetta dal cliente: il posto è tornato libero. */
    public function cancellation(User $customer, Booking $booking): Message
    {
        return $this->templates->message(
            $customer->mailbox(),
            sprintf('Prenotazione disdetta: %s, ore %s', $this->day($booking, withYear: false), $booking->time),
            'booking-cancelled',
            $this->data($customer, $booking, [
                'bookUrl' => $this->bookUrl(),
                'resetUrl' => $this->origin->url(AccountUrls::FORGOT_PASSWORD),
            ]),
        );
    }

    /** Il link per prenotare di nuovo, solo se dal sito si prenota: booking.online, e il titolare non le ha spente. */
    private function bookUrl(): ?string
    {
        return $this->calendar->rules()->online ? $this->origin->url(BookingUrls::DAY) : null;
    }

    /**
     * L'avviso di una prenotazione disdetta dalla palestra: non dice "se non sei stato tu" (non l'ha fatto il cliente),
     * propone un'altra fascia se dal sito si prenota e, se c'è, il WhatsApp della palestra per chiedere.
     * Quando la disdetta viene da un cambio d'orario dice anche perché, in una frase (mai la nota del titolare).
     *
     * @param string|null $reason "Quel giorno la palestra resta chiusa.", "Quel giorno la fascia delle 18:30 non c'è più."
     */
    public function cancellationByGym(User $customer, Booking $booking, ?string $reason = null): Message
    {
        return $this->templates->message(
            $customer->mailbox(),
            sprintf('Prenotazione disdetta dalla palestra: %s, ore %s', $this->day($booking, withYear: false), $booking->time),
            'booking-cancelled-by-gym',
            $this->data($customer, $booking, [
                'bookUrl' => $this->bookUrl(),
                'whatsappUrl' => $this->site->link('whatsapp'),
                'reason' => $reason,
            ]),
        );
    }

    /**
     * L'evento della prenotazione: il suo UID non cambia, così un file nuovo aggiorna quello già nel calendario.
     *
     * @param bool $cancellable il cliente la disdice ancora dal suo account: la descrizione gli dice dove
     */
    private function event(Booking $booking, DateTimeImmutable $now, string $accountUrl, bool $cancellable): IcsCalendar
    {
        $seat = sprintf('Posto %d di %d.', $booking->seat(), $this->calendar->rules()->capacity);

        return (new IcsCalendar('-//4Street//Prenotazione//IT'))->withEvent(new IcsEvent(
            uid: sprintf('prenotazione-%d@%s', $booking->id, $this->origin->host),
            summary: 'Allenamento a ' . $this->site->name,
            location: $this->site->address,
            start: $booking->startsAt,
            allDay: false,
            stamp: $now,
            end: $this->calendar->endOf($booking->startsAt),
            description: $cancellable ? $seat . ' Per disdire: ' . $accountUrl : $seat,
            url: $accountUrl,
        ));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function data(User $customer, Booking $booking, array $data): array
    {
        $name = $customer->greetingName();

        return $data + [
            'greeting' => $name === null ? 'Ciao,' : 'Ciao ' . $name . ',',
            'day' => $this->day($booking, withYear: true),
            'time' => $booking->time->value,
            'endTime' => $this->calendar->endOf($booking->startsAt)->format('H:i'),
            'siteName' => $this->site->name,
            'siteUrl' => (string) $this->origin,
            'address' => $this->site->address,
        ];
    }

    /** "lunedì 7 dicembre 2026" (o senza l'anno, per l'oggetto). */
    private function day(Booking $booking, bool $withYear): string
    {
        return $this->dates->format($booking->date->calendarDay(), withYear: $withYear, capitalize: false);
    }
}
