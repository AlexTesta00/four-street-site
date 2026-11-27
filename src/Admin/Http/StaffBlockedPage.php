<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\BookingStatus;
use FourStreet\Http\Response;
use FourStreet\Text\ItalianDateFormatter;

/**
 * La pagina di una fascia dove il titolare non aggiunge nessuno (templates/pages/admin/blocked.php), con il codice
 * giusto: 404 se la fascia (o il giorno chiesto) non c'è, 409 se c'è ma è già iniziata o è piena. Un solo bottone:
 * il giorno, per scegliere un'altra fascia.
 */
final readonly class StaffBlockedPage
{
    /**
     * @param positive-int $capacity i posti di ogni fascia
     */
    public function __construct(
        private AccountPages $pages,
        private ItalianDateFormatter $dates,
        private int $capacity,
    ) {}

    /**
     * @param BookingResult $result NotFound, Closed o Full
     */
    public function render(BookingResult $result): Response
    {
        return $this->pages->render('blocked', $this->data($result), self::found($result) ? 409 : 404);
    }

    /** La stessa pagina dopo troppi tentativi: 429 e Retry-After. */
    public function tooManyAttempts(BookingResult $result, int $retryAfter): Response
    {
        return $this->pages->tooManyAttempts('blocked', $this->data($result), $retryAfter);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(BookingResult $result): array
    {
        $slot = self::found($result) ? $result->slot() : null;

        return [
            'status' => $result->status,
            'slotDay' => $slot === null ? null : $this->dates->format($slot->date->calendarDay(), withYear: false, capitalize: false),
            'slotTime' => $slot?->time->value,
            'capacity' => $this->capacity,
            'dayUrl' => AdminUrls::day($slot?->date),
            'alert' => null,
        ];
    }

    /** La fascia c'è (piena o iniziata): l'esito ne porta giorno e orario. */
    private static function found(BookingResult $result): bool
    {
        return !in_array($result->status, [BookingStatus::NotFound, BookingStatus::Throttled], true);
    }
}
