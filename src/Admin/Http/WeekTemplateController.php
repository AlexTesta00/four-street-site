<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\TimeField;
use FourStreet\Booking\Schedule\ChangePlan;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Booking\Schedule\ScheduleEditor;
use FourStreet\Booking\Schedule\ScheduleRefusal;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Booking\Weekday;
use FourStreet\Http\Controller;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;

/**
 * /gestione/orari/: la settimana tipo nel pannello del titolare, gli orari d'inizio delle fasce di ogni giorno della
 * settimana. Con GET le sette schede, da lunedì a domenica, ognuna con i suoi orari (un bottone per togliere ciascuno)
 * e il campo per aggiungerne uno; con POST (token CSRF) una modifica alla volta: aggiungere o togliere un orario
 * (campi azione, giorno-settimana, orario). Vale da domani: oggi e il passato restano com'erano, e un giorno con un
 * orario suo non cambia.
 *
 * Fatta, 303 sulla stessa pagina con l'avviso e la scheda del giorno cambiato (?giorno-settimana=1 e #giorno-1: l'avviso
 * sta nella scheda, dove porta il frammento); se toglie un orario con delle prenotazioni, prima la pagina di conferma.
 * Un orario scritto male o che non si può aggiungere: la pagina con l'errore sul campo (422); campi nascosti
 * manomessi: la pagina con l'avviso (404); troppe modifiche in poco tempo: l'avviso (429).
 */
final readonly class WeekTemplateController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private ScheduleEditor $editor,
        private ScheduleBoard $board,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return $this->page($request, ChangeForm::none(), new FormErrors(), report: ChangeReport::fromRequest($request, $this->board), open: ChangeForm::weekdayFrom($request->queryParam(ChangeForm::WEEKDAY)));
        }

        $form = ChangeForm::fromRequest($request);
        $day = $form->weekday();
        // La settimana tipo si cambia solo un orario alla volta: ogni altra azione è un modulo manomesso.
        $action = match ($form->action) {
            ChangeAction::Add, ChangeAction::Remove => $form->action,
            default => null,
        };
        if ($day === null || $action === null) {
            return $this->page($request, ChangeForm::none(), new FormErrors(), 404, ScheduleChanges::tampered());
        }

        if ($action === ChangeAction::Remove) {
            // L'orario di un chip lo scrive la pagina: scritto male, il modulo è manomesso.
            $time = $form->chipTime();
            if ($time === null) {
                return $this->page($request, ChangeForm::none(), new FormErrors(), 404, ScheduleChanges::tampered());
            }
            $change = ScheduleChange::removeWeekdayTime($day, $time);
            $notice = ScheduleNotice::TimeRemoved;
        } else {
            $time = TimeField::parse($form->time);
            if (is_string($time)) {
                return $this->page($request, $form, (new FormErrors())->with(self::timeId($day), $time), 422, open: $day);
            }
            $change = ScheduleChange::addWeekdayTime($day, $time);
            $notice = ScheduleNotice::TimeAdded;
        }

        $outcome = $this->editor->apply(Visitor::of($request)->user(), $change, $form->confirmed);
        $changes = new ScheduleChanges($this->pages);

        return $changes->respond(
            $outcome,
            $notice,
            // Nell'indirizzo anche il giorno della settimana: l'avviso sta nella sua scheda, dove porta il frammento.
            location: static fn(ScheduleNotice $notice, array $cancelled): string => AdminUrls::with(AdminUrls::SCHEDULE, [ChangeForm::WEEKDAY => (string) $day->value, ...$notice->query($cancelled)]) . '#' . self::dayId($day),
            confirm: fn(ChangePlan $plan): Response => $changes->confirm(
                $request,
                $plan,
                new ScheduleDates($this->dates, $this->board->template()->from->addDays(-1)),
                'schedule',
                AdminUrls::SCHEDULE,
                AdminUrls::SCHEDULE . '#' . self::dayId($day),
                [ChangeForm::ACTION => $action->value, ChangeForm::WEEKDAY => (string) $day->value, ChangeForm::TIME => $change->time()->value],
            ),
            // Un problema dell'orario da aggiungere va sul suo campo; il resto (un orario da togliere che non c'è più) sulla scheda.
            refused: fn(ScheduleRefusal $refusal): Response => $this->page(
                $request,
                $form,
                (new FormErrors())->with($refusal->aboutTime && $action === ChangeAction::Add ? self::timeId($day) : self::dayId($day), $refusal->message),
                422,
                open: $day,
            ),
            throttled: fn(int $retryAfter): Response => $this->page($request, $form, new FormErrors(), open: $day, retryAfter: $retryAfter),
        );
    }

    /** L'id della scheda di un giorno, "giorno-1": il frammento dopo una modifica, e dove punta un errore della scheda. */
    public static function dayId(Weekday $day): string
    {
        return 'giorno-' . $day->value;
    }

    /** L'id del campo "Aggiungi un orario" di un giorno, "orario-1". */
    public static function timeId(Weekday $day): string
    {
        return 'orario-' . $day->value;
    }

    /**
     * La pagina, con gli errori, l'avviso dell'indirizzo (nella scheda del giorno cambiato) o quello dei troppi tentativi.
     *
     * @param Weekday|null $open il giorno di cui parla la pagina: l'avviso e gli errori stanno nella sua scheda
     * @param int|null $retryAfter dopo troppi tentativi: la stessa pagina con l'avviso, 429
     */
    private function page(Request $request, ChangeForm $form, FormErrors $errors, int $status = 200, ?Alert $alert = null, ?ChangeReport $report = null, ?Weekday $open = null, ?int $retryAfter = null): Response
    {
        $template = $this->board->template();
        $data = [
            'csrf' => CsrfToken::of($request)->value,
            'template' => $template,
            'form' => $form,
            'errors' => $errors,
            'report' => $report,
            'open' => $open,
            'alert' => $alert,
            'days' => new ScheduleDates($this->dates, $template->from->addDays(-1)),
        ];

        return $retryAfter === null ? $this->pages->render('schedule', $data, $status) : $this->pages->tooManyAttempts('schedule', $data, $retryAfter);
    }
}
