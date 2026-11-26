<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Notice;
use FourStreet\Account\Session\SessionManager;
use FourStreet\Account\Session\Visitor;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;

/**
 * /account/esci/: con POST (e il token CSRF) chiude la sessione di questo browser. Con GET, per chi apre
 * l'indirizzo o ricarica dopo un modulo scaduto, chiede conferma: un link non fa mai uscire nessuno.
 */
final readonly class LogoutController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private SessionManager $sessions,
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->method === 'POST') {
            return Response::seeOther(AccountUrls::to(AccountUrls::LOGIN, notice: Notice::LoggedOut))
                ->withCookie($this->sessions->end($request));
        }
        if (!Visitor::of($request)->isLoggedIn()) {
            return Response::seeOther(AccountUrls::LOGIN);
        }

        return $this->pages->render('logout', ['csrf' => CsrfToken::of($request)->value]);
    }
}
