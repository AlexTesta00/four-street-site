<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\DeliveryStatus;
use FourStreet\Account\Form\EmailForm;
use FourStreet\Account\PasswordReset;
use FourStreet\Account\ReturnPath;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;

/**
 * /account/password-dimenticata/: chiede il link per una nuova password. La pagina dopo è la stessa
 * che l'email abbia un account o no.
 */
final readonly class ForgotPasswordController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private PasswordReset $reset,
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return $this->pages->render('forgot-password', $this->data($request, EmailForm::blank(), ReturnPath::fromQuery($request)));
        }

        $returnTo = ReturnPath::fromBody($request);
        $form = EmailForm::fromRequest($request);
        if (!$form->isValid()) {
            return $this->pages->render('forgot-password', $this->data($request, $form, $returnTo), 422);
        }

        $delivery = $this->reset->request($form->emailAddress(), $returnTo, $request->remoteAddress);
        if ($delivery->status === DeliveryStatus::Throttled) {
            return $this->pages->tooManyAttempts('forgot-password', $this->data($request, $form, $returnTo), $delivery->retryAfter);
        }

        // Sempre la stessa pagina: l'email, se l'indirizzo ha un account, parte dopo la risposta.
        return Response::seeOther(AccountUrls::to(AccountUrls::FORGOT_PASSWORD_SENT, $returnTo));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, EmailForm $form, ReturnPath $returnTo): array
    {
        return ['csrf' => CsrfToken::of($request)->value, 'form' => $form, 'returnTo' => $returnTo, 'alert' => null];
    }
}
