<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use Closure;
use FourStreet\Account\Http\AccountPages;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Booking\RosterEntry;
use FourStreet\Booking\Schedule\CancelledBooking;
use FourStreet\Booking\Schedule\ChangeKind;
use FourStreet\Booking\Schedule\ChangeOutcome;
use FourStreet\Booking\Schedule\ChangePlan;
use FourStreet\Booking\Schedule\ChangeStatus;
use FourStreet\Booking\Schedule\ScheduleRefusal;
use FourStreet\Booking\SlotTime;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\View\Alert;

/**
 * Le risposte a una modifica dell'orario, le stesse per le tre pagine (settimana tipo, settimana precisa, chiusure):
 *
 * - fatta: 303 sulla pagina con l'avviso nell'indirizzo (ricaricare non la rifà); nulla da cambiare: lo stesso,
 *   con l'avviso che lo dice;
 * - da confermare (toglie fasce con delle prenotazioni): 200, la pagina di conferma come risposta al POST, senza
 *   redirect, perché la nota di una chiusura non può andare in un indirizzo. Dice cosa succede con le date vere e chi
 *   è prenotato dove le fasce spariscono, e rimanda la stessa modifica con l'impronta di quelle prenotazioni;
 * - rifiutata, troppi tentativi: la pagina stessa, con il perché sul campo giusto (422) o con l'avviso (429).
 */
final readonly class ScheduleChanges
{
    public function __construct(
        private AccountPages $pages,
    ) {}

    /**
     * La risposta all'esito di una modifica.
     *
     * @param ScheduleNotice $notice l'avviso della modifica fatta
     * @param Closure(ScheduleNotice, list<CancelledBooking>): string $location dove torna la pagina, con l'avviso
     * @param Closure(ChangePlan): Response $confirm la pagina di conferma, con i campi della pagina
     * @param Closure(ScheduleRefusal): Response $refused la pagina con il perché sul campo giusto (422)
     * @param Closure(int): Response $throttled la pagina con l'avviso dei troppi tentativi (429)
     */
    public function respond(ChangeOutcome $outcome, ScheduleNotice $notice, Closure $location, Closure $confirm, Closure $refused, Closure $throttled): Response
    {
        return match ($outcome->status) {
            ChangeStatus::Applied => Response::seeOther($location($notice, $outcome->cancelled)),
            ChangeStatus::Unchanged => Response::seeOther($location(ScheduleNotice::NothingToChange, [])),
            ChangeStatus::NeedsConfirmation => $confirm($outcome->plan()),
            ChangeStatus::Refused => $refused($outcome->refusal()),
            ChangeStatus::Throttled => $throttled($outcome->retryAfter),
        };
    }

    /**
     * La pagina di conferma: la domanda, cosa succede, chi è prenotato dove le fasce spariscono (per giorno) e il modulo
     * che rimanda la stessa modifica con l'impronta di quelle prenotazioni. Se il titolare ne aveva confermate altre
     * (qualcuno ha prenotato o disdetto nel frattempo), un avviso lo dice.
     *
     * @param string $section la voce della navigazione: "schedule" oppure "closures"
     * @param string $action dove va il modulo: la pagina della modifica
     * @param string $back la pagina, per "Annulla"
     * @param array<string, string> $fields i campi della modifica, azione compresa, da rimandare tali e quali
     */
    public function confirm(Request $request, ChangePlan $plan, ScheduleDates $days, string $section, string $action, string $back, array $fields): Response
    {
        $affected = $plan->affected;
        $count = count($affected);
        $guests = count(array_filter($affected, static fn(RosterEntry $entry): bool => $entry->booking->isGuest()));
        [$question, $lead, $verb] = self::wording($plan, $days);
        $previous = $request->bodyParam(ChangeForm::CONFIRMED) ?? '';

        return $this->pages->render('confirm-change', [
            'csrf' => CsrfToken::of($request)->value,
            'question' => $question,
            'lead' => $lead,
            'note' => $plan->change->kind === ChangeKind::Close ? $plan->change->note : null,
            'button' => $verb . ' e disdici ' . ($count === 1 ? 'una prenotazione' : $count . ' prenotazioni'),
            'count' => $count,
            'groups' => self::groups($affected, $days),
            'customers' => $count - $guests,
            'guests' => $guests,
            'fields' => [...$fields, ChangeForm::CONFIRMED => $plan->fingerprint()],
            'action' => $action,
            'back' => $back,
            'section' => $section,
            'alert' => $previous !== '' && $previous !== $plan->fingerprint()
                ? Alert::info('Mentre guardavi qualcuno ha prenotato o disdetto: qui sotto c\'è chi è prenotato adesso. Controlla, e conferma di nuovo.', 'Nel frattempo è cambiato qualcosa')
                : null,
        ]);
    }

    /** Un modulo manomesso (un campo nascosto che la pagina non scrive così): l'avviso della pagina, che risponde 404. */
    public static function tampered(): Alert
    {
        return Alert::error('Il modulo è arrivato incompleto o scritto male, e non è cambiato nulla: ricarica la pagina e riprova.', 'Modifica non valida');
    }

    /**
     * Gli orari a parole: "07:00, 09:30 e 18:30"; "nessuna fascia" se non ce ne sono.
     *
     * @param list<SlotTime> $times
     */
    public static function times(array $times): string
    {
        $values = array_map(static fn(SlotTime $time): string => $time->value, $times);
        $last = array_pop($values);

        return match (true) {
            $last === null => 'nessuna fascia',
            $values === [] => $last,
            default => implode(', ', $values) . ' e ' . $last,
        };
    }

    /**
     * La domanda, cosa succede (con le date vere) e il verbo del bottone.
     *
     * @return array{string, string, string}
     */
    private static function wording(ChangePlan $plan, ScheduleDates $days): array
    {
        $change = $plan->change;
        $gone = 'le prenotazioni qui sotto si disdicono.';

        return match ($change->kind) {
            ChangeKind::RemoveWeekdayTime, ChangeKind::AddWeekdayTime => self::weekdayWording($plan, $days, $gone),
            ChangeKind::RemoveTime => [
                sprintf('Togli le %s di %s?', $change->time(), $days->day($change->from())),
                sprintf('%s la fascia delle %s non c\'è più: %s', $days->day($change->from(), capitalize: true), $change->time(), $gone),
                'Togli',
            ],
            ChangeKind::AddTime => [
                sprintf('Aggiungi le %s a %s?', $change->time(), $days->day($change->from())),
                sprintf('%s ha la fascia delle %s in più, e %s', $days->day($change->from(), capitalize: true), $change->time(), $gone),
                'Aggiungi',
            ],
            ChangeKind::Close => [
                sprintf('Chiudi %s?', $days->range($change->from(), $change->to())),
                sprintf(
                    '%s la palestra resta chiusa, senza fasce: %s',
                    $change->from()->equals($change->to()) ? $days->day($change->from(), capitalize: true) : $days->span($change->from(), $change->to(), capitalize: true),
                    $gone,
                ),
                'Chiudi',
            ],
            // Riaprire disdice solo le prenotazioni rimaste fuori dall'orario in un giorno che la settimana tipo lascia senza fasce.
            ChangeKind::Reopen => [
                sprintf('Riapri %s?', $days->range($change->from(), $change->to())),
                'Si torna agli orari della settimana tipo, dove le fasce qui sotto non ci sono: le loro prenotazioni si disdicono.',
                'Riapri',
            ],
            ChangeKind::BackToWeek => [
                sprintf('Riporti %s alla settimana tipo?', $days->day($change->from())),
                sprintf(
                    '%s torna agli orari della settimana tipo (%s): le altre fasce spariscono, e %s',
                    $days->day($change->from(), capitalize: true),
                    self::times($plan->days === [] ? [] : $plan->days[0]->after),
                    $gone,
                ),
                'Riporta',
            ],
            ChangeKind::CopyWeek => [
                'Copia questa settimana nella prossima?',
                sprintf(
                    '%s prende gli orari e le chiusure di questa settimana (%s): le fasce che non ci sono più spariscono, e %s',
                    $days->span($change->from()->addDays(7), $change->to()->addDays(7), capitalize: true),
                    $days->span($change->from(), $change->to()),
                    $gone,
                ),
                'Copia',
            ],
        };
    }

    /**
     * La settimana tipo cambia da domani, nei giorni che la seguono: "Da domani, giovedì 26 novembre, nei lunedì che
     * seguono la settimana tipo".
     *
     * @return array{string, string, string}
     */
    private static function weekdayWording(ChangePlan $plan, ScheduleDates $days, string $gone): array
    {
        $change = $plan->change;
        $weekday = $change->weekday();
        $time = $change->time();
        $from = $plan->weekday === null ? $days->today->addDays(1) : $plan->weekday->from;
        $when = sprintf('Da domani, %s, %s che seguono la settimana tipo', $days->day($from), ScheduleDates::everyWeekday($weekday));

        return $change->kind === ChangeKind::RemoveWeekdayTime
            ? [sprintf('Togli le %s %s?', $time, ScheduleDates::fromWeekday($weekday)), sprintf('%s la fascia delle %s non c\'è più: %s', $when, $time, $gone), 'Togli']
            : [sprintf('Aggiungi le %s %s?', $time, ScheduleDates::toWeekday($weekday)), sprintf('%s c\'è anche la fascia delle %s, e %s', $when, $time, $gone), 'Aggiungi'];
    }

    /**
     * Le prenotazioni da disdire divise per giorno (arrivano già in ordine di giorno, fascia e posto).
     *
     * @param list<RosterEntry> $entries
     *
     * @return list<array{day: string, entries: list<RosterEntry>}>
     */
    private static function groups(array $entries, ScheduleDates $days): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $date = $entry->booking->date;
            $groups[$date->value] ??= ['day' => $days->day($date, capitalize: true), 'entries' => []];
            $groups[$date->value]['entries'][] = $entry;
        }

        return array_values($groups);
    }
}
