<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\Form\LoginForm;
use FourStreet\Account\Login;
use FourStreet\Account\LoginStatus;
use FourStreet\Account\Notice;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\Session\SessionManager;
use FourStreet\Account\Session\Visitor;
use FourStreet\Account\SignInDestination;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\View\Alert;

/**
 * /account/accedi/: email e password. Riuscito l'accesso si torna alla destinazione (parametro "ritorno");
 * senza, all'account, e il titolare al suo pannello.
 * Un accesso non riuscito ha sempre la stessa risposta, anche per un account non ancora confermato:
 * la pagina ricorda a tutti di confermare l'email e offre un nuovo link, con l'email già scritta.
 */
final readonly class LoginController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private Login $login,
        private SessionManager $sessions,
        private SignInDestination $destination,
    ) {}

    public function __invoke(Request $request): Response
    {
        $submitted = $request->method === 'POST';
        $returnTo = $submitted ? ReturnPath::fromBody($request) : ReturnPath::fromQuery($request);
        $visitor = Visitor::of($request)->user;
        if ($visitor !== null) {
            return Response::seeOther($this->destination->for($visitor, $returnTo));
        }
        if (!$submitted) {
            return $this->pages->render('login', $this->data($request, LoginForm::blank(), $returnTo, Notice::fromRequest($request)?->alert()));
        }

        $form = LoginForm::fromRequest($request);
        if (!$form->isValid()) {
            return $this->pages->render('login', $this->data($request, $form, $returnTo), 422);
        }

        $result = $this->login->attempt($form->emailAddress(), $form->password(), $request->remoteAddress);

        return match ($result->status) {
            LoginStatus::Success => Response::seeOther($this->destination->for($result->user(), $returnTo))
                ->withCookie($this->sessions->start($result->user(), $request)),
            LoginStatus::WrongCredentials => $this->pages->render('login', [
                ...$this->data($request, $form->withWrongCredentials(), $returnTo),
                'offerNewLink' => true,
            ], 422),
            LoginStatus::Throttled => $this->pages->tooManyAttempts('login', $this->data($request, $form, $returnTo), $result->retryAfter),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, LoginForm $form, ReturnPath $returnTo, ?Alert $alert = null): array
    {
        return [
            'csrf' => CsrfToken::of($request)->value,
            'form' => $form,
            'returnTo' => $returnTo,
            'alert' => $alert,
            'offerNewLink' => false,
        ];
    }
}
