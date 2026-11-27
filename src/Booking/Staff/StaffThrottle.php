<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

use FourStreet\Account\User;
use FourStreet\Security\RateLimit\RateLimit;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Security\RateLimit\RateLimitStatus;

/**
 * Il limite dei tentativi del pannello del titolare, a finestre fisse: prenotazioni e disdette contano insieme,
 * per account (un contatore suo, separato da quello delle prenotazioni del cliente). Largo, perché il titolare
 * lavora in fretta, per esempio quando riporta le prenotazioni arrivate su WhatsApp: ferma soltanto un uso
 * fuori misura, come una sessione rubata in mano a un programma che manda email ai clienti.
 * Le modifiche dell'orario e delle regole hanno un contatore a parte, ancora più largo: sistemare la settimana
 * vuol dire tanti piccoli invii (un orario alla volta), e nessuno di questi va contato contro le prenotazioni.
 */
enum StaffThrottle: string
{
    /** Prenotazioni e disdette inviate dallo stesso titolare, riuscite comprese. */
    case ChangesPerAccount = 'pannello-account';

    /** Modifiche dell'orario, chiusure e impostazioni inviate dallo stesso titolare, conferme comprese. */
    case ScheduleChangesPerAccount = 'pannello-orari-account';

    /** Conta un invio del titolare e dice se rientra nel limite. Si conta prima di ogni controllo. */
    public static function hit(RateLimiter $limiter, User $staff): RateLimitStatus
    {
        return $limiter->hit(self::ChangesPerAccount->limit(), (string) $staff->id);
    }

    /** Conta una modifica dell'orario o delle regole e dice se rientra nel limite. Si conta prima di ogni controllo. */
    public static function hitSchedule(RateLimiter $limiter, User $staff): RateLimitStatus
    {
        return $limiter->hit(self::ScheduleChangesPerAccount->limit(), (string) $staff->id);
    }

    public function limit(): RateLimit
    {
        return match ($this) {
            self::ChangesPerAccount => new RateLimit($this->value, 60, 15 * 60),
            self::ScheduleChangesPerAccount => new RateLimit($this->value, 150, 15 * 60),
        };
    }
}
