<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use Closure;
use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\ClosureForm;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\CancelledBooking;
use FourStreet\Booking\Schedule\ChangeOutcome;
use FourStreet\Booking\Schedule\ChangePlan;
use FourStreet\Booking\Schedule\ChangeStatus;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Booking\Schedule\ScheduleEditor;
use FourStreet\Booking\Schedule\ScheduleRefusal;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Clock\Clock;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;

/**
 * /gestione/chiusure/: le chiusure nel pannello del titolare. Con GET il modulo "Chiudi la palestra" (dal, al, nota)
 * e le chiusure in programma, da oggi: i giorni di fila con la stessa nota in una riga sola, ognuna con "Riapri".
 * Prima che il titolare cambi l'orario la prima volta sono i closed_dates di config/site.php: si vedono e si riaprono
 * allo stesso modo. Con POST (token CSRF) una chiusura nuova (azione chiudi, da, a, nota) o una riapertura (azione
 * riapri, da, a). Chiudere un giorno con prenotazioni passa prima dalla pagina di conferma; riaprire di solito non
 * disdice nulla (i giorni tornano alla settimana tipo), tranne le prenotazioni rimaste fuori dall'orario in un giorno
 * che la settimana tipo lascia senza fasce: allora conferma anche lui.
 *
 * Fatta, 303 sulla stessa pagina con l'avviso. Date o nota scritte male, o una chiusura che non si può fare (un giorno
 * passato, prima dell'inaugurazione…): il modulo con l'errore sul campo (422); campi nascosti manomessi: la pagina con
 * l'avviso (404); troppe modifiche in poco tempo: l'avviso (429).
 */
final readonly class ClosuresController implements Controller
{
    /** L'id della parte "In programma": dove punta l'errore di una riapertura. */
    public const string LIST_ID = 'in-programma';

    public function __construct(
        private AccountPages $pages,
        private ScheduleEditor $editor,
        private ScheduleBoard $board,
        private BookingCalendar $calendar,
        private ItalianDateFormatter $dates,
        private Clock $clock,
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return $this->page($request, ClosureForm::blank(), report: ChangeReport::fromRequest($request, $this->board));
        }

        return match (ChangeAction::tryFrom($request->bodyParam(ChangeForm::ACTION) ?? '')) {
            ChangeAction::Close => $this->close($request),
            ChangeAction::Reopen => $this->reopen($request),
            default => $this->page($request, ClosureForm::blank(), 404, ScheduleChanges::tampered()),
        };
    }

    private function close(Request $request): Response
    {
        $form = ClosureForm::fromRequest($request);
        if (!$form->isValid()) {
            return $this->page($request, $form, 422);
        }

        $change = $form->change();
        $outcome = $this->editor->apply(Visitor::of($request)->user(), $change, $request->bodyParam(ChangeForm::CONFIRMED) ?? '');
        $fields = [
            ChangeForm::ACTION => ChangeAction::Close->value,
            ChangeForm::FROM => $change->from()->value,
            ChangeForm::TO => $change->to()->value,
            ...($change->note === null ? [] : [ChangeForm::NOTE => $change->note]),
        ];

        return $this->respond(
            $request,
            $outcome,
            ScheduleNotice::ClosureSaved,
            $fields,
            // Il perché dell'editor riguarda i giorni (passati, prima dell'inaugurazione, oggi già cominciato): va sul primo.
            refused: fn(ScheduleRefusal $refusal): Response => $this->page($request, $form->withError(ClosureForm::FROM, $refusal->message), 422),
            throttled: fn(int $retryAfter): Response => $this->page($request, $form, retryAfter: $retryAfter),
        );
    }

    /**
     * Una chiusura dell'elenco riaperta. Può essere più lunga di quanto si riapre in una volta (giorni di fila chiusi
     * più volte con la stessa nota): allora si riapre un pezzo dopo l'altro, ognuno di al più ScheduleChange::MAX_DAYS.
     * Riaprire non disdice nulla, tranne le prenotazioni rimaste fuori dall'orario in un giorno che la settimana tipo
     * lascia senza fasce: allora c'è la conferma, per quel pezzo.
     */
    private function reopen(Request $request): Response
    {
        // Le date di una riapertura le scrive la pagina: scritte male, il modulo è manomesso.
        $form = ChangeForm::fromRequest($request);
        $from = $form->from();
        $to = $form->to();
        if ($from === null || $to === null || $to->isBefore($from)) {
            return $this->page($request, ClosureForm::blank(), 404, ScheduleChanges::tampered());
        }

        $staff = Visitor::of($request)->user();
        $cancelled = [];
        $applied = false;
        $start = $from;
        do {
            $last = $start->addDays(ScheduleChange::MAX_DAYS - 1);
            $end = $last->isAfter($to) ? $to : $last;
            $outcome = $this->editor->apply($staff, ScheduleChange::reopen($start, $end), $form->confirmed);
            if ($outcome->status === ChangeStatus::Applied) {
                $applied = true;
                $cancelled = [...$cancelled, ...$outcome->cancelled];
            } elseif ($outcome->status !== ChangeStatus::Unchanged) {
                // Una conferma, un rifiuto, troppi tentativi: la risposta è questa; i pezzi già riaperti restano riaperti.
                return $this->respond(
                    $request,
                    $outcome,
                    ScheduleNotice::ClosureRemoved,
                    [ChangeForm::ACTION => ChangeAction::Reopen->value, ChangeForm::FROM => $start->value, ChangeForm::TO => $end->value],
                    refused: fn(ScheduleRefusal $refusal): Response => $this->page($request, ClosureForm::blank()->withError(self::LIST_ID, $refusal->message), 422),
                    throttled: fn(int $retryAfter): Response => $this->page($request, ClosureForm::blank(), retryAfter: $retryAfter),
                );
            }
            $start = $end->addDays(1);
        } while (!$start->isAfter($to));

        return Response::seeOther(self::location($applied ? ScheduleNotice::ClosureRemoved : ScheduleNotice::NothingToChange, $cancelled));
    }

    /**
     * @param array<string, string> $fields i campi della modifica, da rimandare dalla pagina di conferma
     * @param Closure(ScheduleRefusal): Response $refused
     * @param Closure(int): Response $throttled
     */
    private function respond(Request $request, ChangeOutcome $outcome, ScheduleNotice $notice, array $fields, Closure $refused, Closure $throttled): Response
    {
        $changes = new ScheduleChanges($this->pages);

        return $changes->respond(
            $outcome,
            $notice,
            location: self::location(...),
            confirm: fn(ChangePlan $plan): Response => $changes->confirm($request, $plan, $this->days(), 'closures', AdminUrls::CLOSURES, AdminUrls::CLOSURES, $fields),
            refused: $refused,
            throttled: $throttled,
        );
    }

    /**
     * La pagina dopo una modifica, con l'avviso.
     *
     * @param list<CancelledBooking> $cancelled
     */
    private static function location(ScheduleNotice $notice, array $cancelled): string
    {
        return AdminUrls::with(AdminUrls::CLOSURES, $notice->query($cancelled));
    }

    /**
     * Il modulo e le chiusure in programma, con gli errori, l'avviso dell'indirizzo o quello dei troppi tentativi.
     *
     * @param int|null $retryAfter dopo troppi tentativi: la stessa pagina con l'avviso, 429
     */
    private function page(Request $request, ClosureForm $form, int $status = 200, ?Alert $alert = null, ?ChangeReport $report = null, ?int $retryAfter = null): Response
    {
        $data = [
            'csrf' => CsrfToken::of($request)->value,
            'form' => $form,
            'closures' => $this->board->closures(),
            'report' => $report,
            'alert' => $alert,
            'firstDay' => $this->firstDay(),
            'days' => $this->days(),
        ];

        return $retryAfter === null ? $this->pages->render('closures', $data, $status) : $this->pages->tooManyAttempts('closures', $data, $retryAfter);
    }

    private function today(): LocalDate
    {
        return $this->calendar->today($this->clock->now());
    }

    /** Il primo giorno che si chiude: oggi, oppure l'inaugurazione se deve ancora venire (prima non ci sono fasce). */
    private function firstDay(): LocalDate
    {
        $today = $this->today();
        $opening = $this->calendar->openingDay();

        return $opening->isAfter($today) ? $opening : $today;
    }

    private function days(): ScheduleDates
    {
        return new ScheduleDates($this->dates, $this->today());
    }
}
