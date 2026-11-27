<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Form\ClosureNote;
use FourStreet\Admin\Form\TimeField;
use FourStreet\Booking\BookingResult;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\ChangePlan;
use FourStreet\Booking\Schedule\ScheduleChange;
use FourStreet\Booking\Schedule\ScheduleEditor;
use FourStreet\Booking\Schedule\ScheduleRefusal;
use FourStreet\Booking\Staff\ScheduleBoard;
use FourStreet\Booking\Staff\WeekSheet;
use FourStreet\Booking\Weekday;
use FourStreet\Http\Controller;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /gestione/orari/settimana/?da=AAAA-MM-GG: una settimana precisa nel pannello del titolare, da lunedì a domenica.
 * Con GET i sette giorni, ognuno con il suo orario e da dove viene (la settimana tipo, un orario suo, una chiusura),
 * le persone prenotate per fascia e cosa si può fare; il giorno di "aperto" si apre da solo (arriva dopo ogni modifica
 * e dal giorno del pannello). Senza "da", la settimana di oggi; un giorno che non è lunedì porta (303) alla settimana
 * che lo contiene; un giorno scritto male, o oltre la portata del pannello: 404.
 *
 * Con POST (token CSRF) una modifica di un giorno (campi azione, giorno, orario, nota): aggiungere o togliere un orario,
 * chiudere (con la nota facoltativa), riaprire, tornare alla settimana tipo; oppure copiare la settimana nella
 * successiva (azione copia, da). Fatta, 303 sulla settimana con il giorno aperto e l'avviso (#giorno-AAAA-MM-GG);
 * la copia porta alla settimana dopo. Se toglie fasce con delle prenotazioni, prima la pagina di conferma. Un campo
 * scritto male o una modifica che non si può fare: la settimana con l'errore sul campo (422); campi nascosti
 * manomessi: la pagina di un indirizzo che non c'è (404); troppe modifiche in poco tempo: l'avviso (429).
 */
final readonly class WeekController implements Controller
{
    /** L'id della parte "Copia nella settimana dopo", dove punta un errore della copia. */
    public const string COPY_ID = 'copia';

    public function __construct(
        private AccountPages $pages,
        private ScheduleEditor $editor,
        private ScheduleBoard $board,
        private StaffBlockedPage $blocked,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        return $request->method === 'POST' ? $this->submit($request) : $this->show($request);
    }

    /** L'id del giorno nella pagina, "giorno-2026-12-09": il frammento dopo una modifica, e dove punta un errore del giorno. */
    public static function dayId(LocalDate $date): string
    {
        return 'giorno-' . $date->value;
    }

    /** L'id del campo "Aggiungi un orario" di un giorno. */
    public static function timeId(LocalDate $date): string
    {
        return 'orario-' . $date->value;
    }

    /** L'id del campo della nota per chiudere un giorno. */
    public static function noteId(LocalDate $date): string
    {
        return 'nota-' . $date->value;
    }

    private function show(Request $request): Response
    {
        $value = $request->queryParam(AdminUrls::WEEK_PARAMETER);
        $requested = $value === null ? null : LocalDate::tryFrom($value);
        // Un giorno scritto male, o una settimana oltre la portata del pannello: non c'è.
        $sheet = $value !== null && $requested === null ? null : $this->board->week($requested);
        if ($sheet === null) {
            return $this->blocked->render(BookingResult::notFound());
        }

        $open = LocalDate::tryFrom($request->queryParam(AdminUrls::OPEN_PARAMETER) ?? '');
        if ($requested !== null && !$requested->equals($sheet->monday)) {
            // La settimana comincia di lunedì: un altro giorno porta alla sua, con lo stesso giorno aperto.
            return Response::seeOther(AdminUrls::week($sheet->monday, $open));
        }

        return $this->page($request, $sheet, ChangeForm::none(), new FormErrors(), open: self::inWeek($sheet, $open), report: ChangeReport::fromRequest($request, $this->board));
    }

    private function submit(Request $request): Response
    {
        $form = ChangeForm::fromRequest($request);
        $action = $form->action;
        if ($action === ChangeAction::Copy) {
            return $this->copy($request, $form);
        }

        // Il giorno lo scrive la pagina: scritto male, o oltre la portata del pannello, il modulo è manomesso.
        $date = $form->day();
        $sheet = $date === null ? null : $this->board->week($date);
        if ($action === null || $date === null || $sheet === null) {
            return $this->blocked->render(BookingResult::notFound());
        }

        $errorId = self::dayId($date);
        switch ($action) {
            case ChangeAction::Add:
                $time = TimeField::parse($form->time);
                if (is_string($time)) {
                    return $this->page($request, $sheet, $form, (new FormErrors())->with(self::timeId($date), $time), 422, $date);
                }
                $change = ScheduleChange::addTime($date, $time);
                $notice = ScheduleNotice::TimeAdded;
                $errorId = self::timeId($date);
                $fields = [ChangeForm::TIME => $time->value];
                break;

            case ChangeAction::Remove:
                // L'orario di un chip lo scrive la pagina: scritto male, il modulo è manomesso.
                $time = $form->chipTime();
                if ($time === null) {
                    return $this->blocked->render(BookingResult::notFound());
                }
                $change = ScheduleChange::removeTime($date, $time);
                $notice = ScheduleNotice::TimeRemoved;
                $fields = [ChangeForm::TIME => $time->value];
                break;

            case ChangeAction::Close:
                $note = ClosureNote::parse($form->note);
                if ($note->error !== null) {
                    return $this->page($request, $sheet, $form, (new FormErrors())->with(self::noteId($date), $note->error), 422, $date);
                }
                $change = ScheduleChange::close($date, $date, $note->value);
                $notice = ScheduleNotice::DayClosed;
                $fields = $note->value === null ? [] : [ChangeForm::NOTE => $note->value];
                break;

            case ChangeAction::Reopen:
                $change = ScheduleChange::reopen($date, $date);
                $notice = ScheduleNotice::DayReopened;
                $fields = [];
                break;

            case ChangeAction::BackToWeek:
                $change = ScheduleChange::backToWeek($date);
                $notice = ScheduleNotice::BackToWeek;
                $fields = [];
                break;

            default:
                return $this->blocked->render(BookingResult::notFound());
        }

        $outcome = $this->editor->apply(Visitor::of($request)->user(), $change, $form->confirmed);
        $changes = new ScheduleChanges($this->pages);

        return $changes->respond(
            $outcome,
            $notice,
            location: static fn(ScheduleNotice $notice, array $cancelled): string => AdminUrls::week($sheet->monday, $date, $notice->query($cancelled)) . '#' . self::dayId($date),
            confirm: fn(ChangePlan $plan): Response => $changes->confirm(
                $request,
                $plan,
                new ScheduleDates($this->dates, $sheet->today),
                'schedule',
                AdminUrls::week($sheet->monday, $date),
                AdminUrls::week($sheet->monday, $date) . '#' . self::dayId($date),
                [ChangeForm::ACTION => $action->value, ChangeForm::DAY => $date->value, ...$fields],
            ),
            // Un problema dell'orario aggiunto va sul suo campo; il resto (il giorno è passato, una fascia è già cominciata…) sul giorno.
            refused: fn(ScheduleRefusal $refusal): Response => $this->page(
                $request,
                $sheet,
                $form,
                (new FormErrors())->with($refusal->aboutTime ? $errorId : self::dayId($date), $refusal->message),
                422,
                $date,
            ),
            throttled: fn(int $retryAfter): Response => $this->page($request, $sheet, $form, new FormErrors(), open: $date, retryAfter: $retryAfter),
        );
    }

    /** La settimana copiata nella successiva: poi, alla settimana dopo con l'avviso. */
    private function copy(Request $request, ChangeForm $form): Response
    {
        // Il lunedì lo scrive la pagina: scritto male, o un altro giorno, il modulo è manomesso.
        $monday = $form->from();
        $sheet = $monday === null || $monday->weekday() !== Weekday::Monday ? null : $this->board->week($monday);
        if ($monday === null || $sheet === null) {
            return $this->blocked->render(BookingResult::notFound());
        }

        $outcome = $this->editor->apply(Visitor::of($request)->user(), ScheduleChange::copyWeek($monday), $form->confirmed);
        $changes = new ScheduleChanges($this->pages);

        return $changes->respond(
            $outcome,
            ScheduleNotice::WeekCopied,
            location: static fn(ScheduleNotice $notice, array $cancelled): string => AdminUrls::week($monday->addDays(7), null, $notice->query($cancelled)),
            confirm: fn(ChangePlan $plan): Response => $changes->confirm(
                $request,
                $plan,
                new ScheduleDates($this->dates, $sheet->today),
                'schedule',
                AdminUrls::week($monday),
                AdminUrls::week($monday) . '#' . self::COPY_ID,
                [ChangeForm::ACTION => ChangeAction::Copy->value, ChangeForm::FROM => $monday->value],
            ),
            refused: fn(ScheduleRefusal $refusal): Response => $this->page($request, $sheet, $form, (new FormErrors())->with(self::COPY_ID, $refusal->message), 422),
            throttled: fn(int $retryAfter): Response => $this->page($request, $sheet, $form, new FormErrors(), retryAfter: $retryAfter),
        );
    }

    /**
     * La settimana, con i giorni, gli errori, l'avviso dell'indirizzo o quello dei troppi tentativi.
     *
     * @param LocalDate|null $open il giorno aperto: quello cambiato, o quello chiesto con "aperto"
     * @param int|null $retryAfter dopo troppi tentativi: la stessa pagina con l'avviso, 429
     */
    private function page(Request $request, WeekSheet $sheet, ChangeForm $form, FormErrors $errors, int $status = 200, ?LocalDate $open = null, ?ChangeReport $report = null, ?int $retryAfter = null): Response
    {
        $days = new ScheduleDates($this->dates, $sheet->today);
        $data = [
            'csrf' => CsrfToken::of($request)->value,
            'sheet' => $sheet,
            'heading' => $days->span($sheet->monday, $sheet->sunday(), capitalize: true),
            'open' => $open,
            'form' => $form,
            'errors' => $errors,
            'report' => $report,
            'alert' => null,
            'dates' => $this->dates,
            'days' => $days,
        ];

        return $retryAfter === null ? $this->pages->render('week', $data, $status) : $this->pages->tooManyAttempts('week', $data, $retryAfter);
    }

    /** Il giorno, se è in quella settimana. */
    private static function inWeek(WeekSheet $sheet, ?LocalDate $date): ?LocalDate
    {
        return $date !== null && !$date->isBefore($sheet->monday) && !$date->isAfter($sheet->sunday()) ? $date : null;
    }
}
