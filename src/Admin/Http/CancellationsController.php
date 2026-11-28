<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\AccountPages;
use FourStreet\Account\Session\Visitor;
use FourStreet\Admin\AdminUrls;
use FourStreet\Booking\Staff\CancellationSheet;
use FourStreet\Booking\Staff\GymCancellations;
use FourStreet\Booking\Staff\ResendStatus;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;
use FourStreet\View\Alert;

/**
 * /gestione/disdette/: le prenotazioni disdette dalla palestra la cui fascia deve ancora cominciare, per giorno, ognuna
 * con com'è andata l'email della palestra (Booking\Staff\GymCancellations). Con GET la pagina, una lettura sola del
 * database; dopo un invio, l'avviso dice quante email sono partite (dall'indirizzo, un numero) e quante restano (dalla
 * pagina stessa, com'è adesso). Con POST (token CSRF) "Invia le email rimaste", poi 303 sulla stessa pagina con
 * l'avviso: ricaricare non rimanda nulla. Troppi invii in poco tempo (il contatore largo delle modifiche dell'orario):
 * la pagina con l'avviso (429).
 */
final readonly class CancellationsController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private GymCancellations $cancellations,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->method !== 'POST') {
            $sheet = $this->cancellations->upcoming();

            return $this->page($request, $sheet, ResendNotice::fromRequest($request)?->alert(ResendNotice::sentFrom($request), $sheet->toSend()));
        }

        $outcome = $this->cancellations->resend(Visitor::of($request)->user());
        if ($outcome->status === ResendStatus::Throttled) {
            return $this->page($request, $this->cancellations->upcoming(), retryAfter: $outcome->retryAfter);
        }
        $notice = ResendNotice::of($outcome);

        return Response::seeOther(AdminUrls::with(AdminUrls::CANCELLATIONS, $notice->query($outcome->sent)));
    }

    /**
     * La pagina, con l'avviso dopo un invio o quello dei troppi tentativi.
     *
     * @param int|null $retryAfter dopo troppi tentativi: la stessa pagina con l'avviso, 429
     */
    private function page(Request $request, CancellationSheet $sheet, ?Alert $alert = null, ?int $retryAfter = null): Response
    {
        $data = [
            'csrf' => CsrfToken::of($request)->value,
            'sheet' => $sheet,
            'alert' => $alert,
            'days' => new ScheduleDates($this->dates, $sheet->today),
        ];

        return $retryAfter === null ? $this->pages->render('cancellations', $data) : $this->pages->tooManyAttempts('cancellations', $data, $retryAfter);
    }
}
