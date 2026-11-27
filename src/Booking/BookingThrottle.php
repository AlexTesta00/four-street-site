<?php

declare(strict_types=1);

namespace FourStreet\Booking;

use FourStreet\Account\User;
use FourStreet\Security\IpAddress;
use FourStreet\Security\RateLimit\RateLimit;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Security\RateLimit\RateLimitStatus;
use SensitiveParameter;

/**
 * I limiti di tentativi delle prenotazioni, a finestre fisse: prenotazioni e disdette contano insieme.
 * L'indirizzo IP non si salva mai, solo la sua impronta con chiave; l'account conta per il suo id.
 */
enum BookingThrottle: string
{
    /** Prenotazioni e disdette inviate dallo stesso account, riuscite comprese. */
    case ChangesPerAccount = 'prenotazioni-account';

    /**
     * Prenotazioni e disdette dallo stesso indirizzo IP: largo, perché chi si allena nella stessa
     * palestra (o nello stesso ufficio) può uscire su internet con lo stesso indirizzo.
     */
    case ChangesPerIp = 'prenotazioni-ip';

    /**
     * Conta un invio (una prenotazione o una disdetta) per l'account e per l'indirizzo IP, e dice se
     * rientra in entrambi i limiti. Si conta prima di ogni controllo: tanti invii insieme non passano.
     */
    public static function hit(RateLimiter $limiter, User $customer, #[SensitiveParameter] string $ip): RateLimitStatus
    {
        return $limiter->hit(self::ChangesPerAccount->limit(), (string) $customer->id)
            ->and($limiter->hit(self::ChangesPerIp->limit(), IpAddress::rateLimitSubject($ip)));
    }

    public function limit(): RateLimit
    {
        return match ($this) {
            self::ChangesPerAccount => new RateLimit($this->value, 12, 15 * 60),
            self::ChangesPerIp => new RateLimit($this->value, 60, 15 * 60),
        };
    }
}
