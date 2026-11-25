<?php

declare(strict_types=1);

namespace FourStreet\Schedule;

use InvalidArgumentException;

/**
 * Secondi che mancano, scomposti in giorni, ore, minuti e secondi.
 */
final readonly class Countdown
{
    private const int MINUTE = 60;
    private const int HOUR = 3600;
    private const int DAY = 86400;

    public function __construct(
        public int $totalSeconds,
    ) {
        if ($totalSeconds < 0) {
            throw new InvalidArgumentException(sprintf('Il countdown non può essere negativo: %d.', $totalSeconds));
        }
    }

    public function days(): int
    {
        return intdiv($this->totalSeconds, self::DAY);
    }

    public function hours(): int
    {
        return intdiv($this->totalSeconds % self::DAY, self::HOUR);
    }

    public function minutes(): int
    {
        return intdiv($this->totalSeconds % self::HOUR, self::MINUTE);
    }

    public function seconds(): int
    {
        return $this->totalSeconds % self::MINUTE;
    }

    /**
     * Le quattro caselle del countdown, nell'ordine in cui compaiono in pagina.
     *
     * @return list<CountdownUnit>
     */
    public function units(): array
    {
        return [
            new CountdownUnit('giorni', 'Giorni', $this->days()),
            new CountdownUnit('ore', 'Ore', $this->hours()),
            new CountdownUnit('minuti', 'Minuti', $this->minutes()),
            new CountdownUnit('secondi', 'Secondi', $this->seconds()),
        ];
    }

    /**
     * Quanto manca in breve: i giorni interi, oppure le ore (per eccesso) nell'ultimo giorno.
     */
    public function roughly(): TimeLeft
    {
        if ($this->totalSeconds >= self::DAY) {
            $days = $this->days();

            return new TimeLeft($days, $days === 1 ? 'giorno' : 'giorni');
        }

        $hours = intdiv($this->totalSeconds + self::HOUR - 1, self::HOUR);

        return new TimeLeft($hours, $hours === 1 ? 'ora' : 'ore');
    }
}
