<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Form\NewPasswordForm;
use FourStreet\Account\Notice;
use FourStreet\Account\PasswordPolicy;
use FourStreet\Account\PasswordReset;
use FourStreet\Account\ResetTicket;
use FourStreet\Account\Session\SessionManager;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;

/**
 * /account/nuova-password/?token=…: il link dell'email per la password dimenticata.
 * Il token passa nel campo nascosto del modulo; la pagina non lo manda come Referer a nessuno.
 * Dopo il cambio si entra, e le sessioni degli altri dispositivi sono chiuse.
 */
final readonly class ResetPasswordController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private PasswordReset $reset,
        private PasswordPolicy $policy,
        private SessionManager $sessions,
    ) {}

    public function __invoke(Request $request): Response
    {
        $submitted = $request->method === 'POST';
        $token = $submitted ? $request->bodyParam(AccountUrls::TOKEN_PARAMETER) : $request->queryParam(AccountUrls::TOKEN_PARAMETER);
        $ticket = $this->reset->ticket($token);
        if ($ticket === null) {
            return $this->pages->render('reset-invalid', [], 410);
        }
        if (!$submitted) {
            return $this->form($request, $ticket, NewPasswordForm::blank());
        }

        $form = NewPasswordForm::fromRequest($request, $this->policy);
        if (!$form->isValid()) {
            return $this->form($request, $ticket, $form, 422);
        }

        $user = $this->reset->complete($ticket, $form->password());
        if ($user === null) {
            return $this->pages->render('reset-invalid', [], 410);
        }

        $location = $ticket->returnTo->isDefault()
            ? AccountUrls::to(AccountUrls::HOME, notice: Notice::PasswordChanged)
            : $ticket->returnTo->value;

        return Response::seeOther($location)->withCookie($this->sessions->start($user, $request));
    }

    private function form(Request $request, ResetTicket $ticket, NewPasswordForm $form, int $status = 200): Response
    {
        return $this->pages->render('reset-password', [
            'csrf' => CsrfToken::of($request)->value,
            'form' => $form,
            'token' => $ticket->token->value,
            'email' => $ticket->user->email->value,
        ], $status);
    }
}
