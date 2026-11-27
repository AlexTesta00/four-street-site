<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\SettingsForm;
use FourStreet\Booking\BookingCalendar;
use FourStreet\Booking\BookingRules;
use FourStreet\Booking\LocalDate;
use FourStreet\Booking\Schedule\RulesChange;
use FourStreet\Booking\Schedule\RulesEditor;
use FourStreet\Booking\SlotTime;
use FourStreet\Clock\Clock;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;
use LogicException;

/**
 * /gestione/impostazioni/: le regole delle prenotazioni che il titolare cambia. Con GET il modulo con le regole in vigore
 * (quelle del file, con al loro posto i valori già salvati dal pannello), ogni campo con l'aiuto che dice l'effetto
 * con i valori veri; con POST (token CSRF) il salvataggio, tutte insieme, poi 303 sulla stessa pagina con l'avviso:
 * ricaricare non salva di nuovo. Un modulo scritto male torna con gli errori sul campo (422); troppi salvataggi in poco
 * tempo (il contatore largo delle modifiche dell'orario) lo stesso modulo con l'avviso (429).
 * Posti per fascia e durata della fascia restano nel file: la pagina li mostra, fissi.
 */
final readonly class SettingsController implements Controller
{
    /** L'avviso dopo il salvataggio ("?avviso=impostazioni-salvate"): sceglie solo un testo fisso. */
    public const string SAVED = 'impostazioni-salvate';

    /**
     * @param BookingCalendar $calendar le regole in vigore (calendar->rules())
     * @param BookingRules $file le regole di config/site.php: l'interruttore generale, i posti e la durata della fascia
     */
    public function __construct(
        private AccountPages $pages,
        private RulesEditor $editor,
        private BookingCalendar $calendar,
        private BookingRules $file,
        private ItalianDateFormatter $dates,
        private Clock $clock,
    ) {}

    public function __invoke(Request $request): Response
    {
        $current = RulesChange::of($this->calendar->rules());
        if ($request->method !== 'POST') {
            $saved = $request->queryParam(AdminNotice::PARAMETER) === self::SAVED;

            return $this->form($request, SettingsForm::of($current), $current, alert: $saved ? self::savedAlert() : null);
        }

        $form = SettingsForm::fromRequest($request, $current, $this->file->online);
        if (!$form->isValid()) {
            return $this->form($request, $form, $current, 422);
        }

        $outcome = $this->editor->save(Visitor::of($request)->user(), $form->rules());
        if (!$outcome->saved) {
            return $this->form($request, $form, $current, retryAfter: $outcome->retryAfter);
        }

        return Response::seeOther(AdminUrls::with(AdminUrls::SETTINGS, [AdminNotice::PARAMETER => self::SAVED]));
    }

    private static function savedAlert(): Alert
    {
        return Alert::success('Valgono da subito per le prossime prenotazioni dei clienti; quelle già fatte restano come sono.', 'Impostazioni salvate');
    }

    /**
     * Il modulo, con gli aiuti calcolati sulle regole in vigore.
     *
     * @param int|null $retryAfter dopo troppi salvataggi: lo stesso modulo con l'avviso, 429
     */
    private function form(Request $request, SettingsForm $form, RulesChange $current, int $status = 200, ?Alert $alert = null, ?int $retryAfter = null): Response
    {
        $data = [
            'csrf' => CsrfToken::of($request)->value,
            'form' => $form,
            'switchable' => $this->file->online,
            'bookingDeadlines' => self::options(SettingsForm::deadlines($current->bookingClosesMinutesBefore)),
            'cancelDeadlines' => self::options(SettingsForm::deadlines($current->cancelClosesMinutesBefore)),
            'hints' => $this->hints($current),
            'capacity' => $this->file->capacity,
            'slotMinutes' => $this->file->slotMinutes,
            'alert' => $alert,
        ];

        return $retryAfter === null ? $this->pages->render('settings', $data, $status) : $this->pages->tooManyAttempts('settings', $data, $retryAfter);
    }

    /**
     * Le scelte di "fino a quando" per il menu.
     *
     * @param list<int> $minutes
     *
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $minutes): array
    {
        return array_map(static fn(int $value): array => ['value' => (string) $value, 'label' => SettingsForm::deadlineLabel($value)], $minutes);
    }

    /**
     * Gli aiuti dei campi: che effetto hanno adesso le regole in vigore, a parole e con i valori veri. Dopo un errore
     * restano quelli delle regole in vigore, non dei valori scritti: dicono com'è adesso.
     *
     * @return array<string, string> nome del campo => aiuto
     */
    private function hints(RulesChange $current): array
    {
        $example = $this->exampleTime()->value;
        $max = $current->maxUpcoming === 1
            ? 'Adesso ogni cliente ha al massimo una prenotazione in arrivo: quando passa, o la disdice, ne prenota un\'altra.'
            : sprintf('Adesso ogni cliente ha al massimo %d prenotazioni in arrivo, una al giorno: quando una passa, o la disdice, ne prenota un\'altra.', $current->maxUpcoming);

        return [
            SettingsForm::DAYS_AHEAD => $this->daysHint($current->daysAhead),
            SettingsForm::BOOKING_CLOSES => sprintf('Adesso la fascia delle %s si prenota %s.', $example, $this->until($current->bookingClosesMinutesBefore)),
            SettingsForm::CANCEL_CLOSES => sprintf('Adesso chi ha la fascia delle %s la disdice dal suo account %s', $example, $this->until($current->cancelClosesMinutesBefore))
                . ($current->cancelClosesMinutesBefore === 0 ? '.' : '; dopo, la disdici tu dal pannello.'),
            SettingsForm::MAX_UPCOMING => $max . sprintf(' Da 1 a %d.', RulesChange::MAX_UPCOMING),
        ];
    }

    /**
     * Da quando a quando prenotano i clienti adesso, con i giorni in vigore: da oggi, oppure dall'inaugurazione se deve
     * ancora venire, fino a oggi più quei giorni (come BookingCalendar::days()).
     */
    private function daysHint(int $daysAhead): string
    {
        $today = $this->calendar->today($this->clock->now());
        $opening = $this->calendar->openingDay();
        $last = $today->addDays($daysAhead);

        return match (true) {
            $opening->isAfter($last) => sprintf(
                'Adesso i clienti non prenotano ancora: cominciano %s, per le fasce dall\'inaugurazione (%s) in poi.',
                $this->day($opening->addDays(-$daysAhead), $today),
                $this->day($opening, $today),
            ),
            $opening->isAfter($today) => sprintf('Adesso i clienti vedono e prenotano dall\'inaugurazione, %s, fino a %s.', $this->day($opening, $today), $this->day($last, $today)),
            $daysAhead === 0 => 'Adesso i clienti prenotano solo le fasce di oggi.',
            default => sprintf('Adesso i clienti vedono e prenotano da oggi fino a %s.', $this->day($last, $today)),
        } . sprintf(' Da 0 (solo oggi) a %d.', BookingRules::MAX_DAYS_AHEAD);
    }

    /**
     * L'orario degli esempi negli aiuti: la fascia di esempio scelta della landing (config/site.php, default_slot),
     * un orario che il titolare riconosce.
     */
    private function exampleTime(): SlotTime
    {
        // Slot accetta solo orari scritti come SlotTime: qui non si arriva mai al throw.
        return SlotTime::tryFrom($this->file->examples->defaultSlot()->time) ?? throw new LogicException('Orario della fascia di esempio non valido.');
    }

    /**
     * Fino a quando, per la fascia d'esempio, con quei minuti prima dell'inizio: "fino alle 18:00",
     * "fino alle 18:30 del giorno prima", "fino alle 18:30, quando comincia". Sono minuti sull'orologio: per un esempio
     * il cambio dell'ora non conta.
     */
    private function until(int $minutes): string
    {
        $start = $this->exampleTime();
        if ($minutes === 0) {
            return sprintf('fino alle %s, quando comincia', $start->value);
        }

        $at = $start->minutes - $minutes;
        $daysBefore = 0;
        while ($at < 0) {
            $at += 24 * 60;
            ++$daysBefore;
        }
        $time = sprintf('%02d:%02d', intdiv($at, 60), $at % 60);

        return match ($daysBefore) {
            0 => sprintf('fino alle %s', $time),
            1 => sprintf('fino alle %s del giorno prima', $time),
            2 => sprintf('fino alle %s di due giorni prima', $time),
            default => sprintf('fino alle %s di %d giorni prima', $time, $daysBefore),
        };
    }

    /** "mercoledì 9 dicembre", con l'anno se non è quello di oggi. */
    private function day(LocalDate $date, LocalDate $today): string
    {
        $calendarDay = $date->calendarDay();

        return $this->dates->format($calendarDay, withYear: $calendarDay->format('Y') !== $today->calendarDay()->format('Y'), capitalize: false);
    }
}
