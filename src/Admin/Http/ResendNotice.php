<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Booking\Staff\GymEmailStop;
use FourStreet\Booking\Staff\ResendOutcome;
use FourStreet\Booking\Staff\ResendStatus;
use FourStreet\Http\Request;
use FourStreet\View\Alert;
use LogicException;

/**
 * L'avviso dopo "Invia le email rimaste" ("?avviso=email-inviate&partite=3"): il parametro sceglie solo fra questi testi
 * fissi e l'indirizzo porta solo quante email sono partite, un numero. Quante restano lo dice la pagina, che le rilegge
 * dal database: com'è adesso, anche se nel frattempo un altro invio ne ha mandate.
 */
enum ResendNotice: string
{
    public const string PARAMETER = AdminNotice::PARAMETER;

    /** Quante email sono partite. */
    public const string SENT = 'partite';

    /** Il conto scritto come lo scrive la pagina: un numero intero, non enorme. */
    private const string COUNT_PATTERN = '/\A(?:0|[1-9]\d{0,5})\z/';

    /** Il giro è arrivato in fondo: le email rimaste, se ce ne sono, sono quelle che non sono partite. */
    case Sent = 'email-inviate';

    /** Finito il tempo degli invii della richiesta: le altre sono rimaste in attesa. */
    case TimeUp = 'email-tempo-finito';

    /** Due invii falliti di fila: il server di posta non risponde. */
    case MailDown = 'email-server-fermo';

    /** Non c'era nessuna email da mandare. */
    case NothingToSend = 'nessuna-email-da-inviare';

    /** L'avviso di com'è andato l'invio (mai Throttled: quello resta sulla pagina, 429). */
    public static function of(ResendOutcome $outcome): self
    {
        return match ($outcome->status) {
            ResendStatus::NothingToSend => self::NothingToSend,
            ResendStatus::Sent => match ($outcome->stopped) {
                null => self::Sent,
                GymEmailStop::TimeUp => self::TimeUp,
                GymEmailStop::MailDown => self::MailDown,
            },
            ResendStatus::Throttled => throw new LogicException('Troppi tentativi: nessun avviso dopo un redirect.'),
        };
    }

    public static function fromRequest(Request $request): ?self
    {
        return self::tryFrom($request->queryParam(self::PARAMETER) ?? '');
    }

    /** Quante email sono partite, dall'indirizzo: un conto scritto male non conta. */
    public static function sentFrom(Request $request): int
    {
        $count = $request->queryParam(self::SENT) ?? '';

        return preg_match(self::COUNT_PATTERN, $count) === 1 ? (int) $count : 0;
    }

    /**
     * I parametri dell'indirizzo dopo l'invio: l'avviso e quante email sono partite.
     *
     * @return array<string, string>
     */
    public function query(int $sent): array
    {
        return $this === self::NothingToSend ? [self::PARAMETER => $this->value] : [self::PARAMETER => $this->value, self::SENT => (string) $sent];
    }

    /**
     * @param int $sent quante email sono partite (dall'indirizzo)
     * @param int $remaining quante restano da mandare adesso, in attesa o non partite (dalla pagina)
     */
    public function alert(int $sent, int $remaining): Alert
    {
        $done = match ($sent) {
            0 => 'Non è partita nessuna email.',
            1 => 'È partita un\'email.',
            default => sprintf('Sono partite %d email.', $sent),
        };
        $left = $remaining === 1 ? 'Ne resta una' : sprintf('Ne restano %d', $remaining);

        return match (true) {
            $this === self::NothingToSend => Alert::info('Non c\'era nessuna email in attesa o non partita da inviare.', 'Nulla da inviare'),
            // Nessuna partita da qui e nessuna rimasta: le ha prese un altro invio, o le loro fasce sono cominciate.
            $remaining === 0 && $sent === 0 => Alert::info($done . ' Non ne resta nessuna da inviare.', 'Nulla da inviare'),
            $remaining === 0 => Alert::success($done . ' Non ne resta nessuna da inviare.', 'Email inviate'),
            $this === self::TimeUp => Alert::info($done . ' ' . $left . ': si mandano poche email per volta, perché la pagina non resti ad aspettare. Premi di nuovo «Invia le email rimaste».', 'Email inviate in parte'),
            $this === self::MailDown => Alert::error('Il server di posta non risponde. ' . $done . ' ' . $left . ': riprova più tardi, oppure avvisa tu quei clienti.', 'Email non partite'),
            default => Alert::info($done . ' ' . $left . ' da inviare: riprova più tardi, oppure avvisa tu quei clienti.', 'Email inviate in parte'),
        };
    }
}
