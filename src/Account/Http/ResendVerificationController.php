<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\DeliveryStatus;
use FourStreet\Account\EmailVerification;
use FourStreet\Account\Form\EmailForm;
use FourStreet\Account\ReturnPath;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;

/**
 * /account/verifica/nuovo-link/: un nuovo link di conferma dell'email. Ci arriva anche il bottone
 * "Mandami un nuovo link" della pagina di accesso non riuscito, con l'email già scritta.
 */
final readonly class ResendVerificationController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private EmailVerification $verification,
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return $this->pages->render('resend', $this->data($request, EmailForm::blank(), ReturnPath::fromQuery($request)));
        }

        $returnTo = ReturnPath::fromBody($request);
        $form = EmailForm::fromRequest($request);
        if (!$form->isValid()) {
            return $this->pages->render('resend', $this->data($request, $form, $returnTo), 422);
        }

        $delivery = $this->verification->resend($form->emailAddress(), $returnTo, $request->remoteAddress);
        if ($delivery->status === DeliveryStatus::Throttled) {
            return $this->pages->tooManyAttempts('resend', $this->data($request, $form, $returnTo), $delivery->retryAfter);
        }

        // Sempre la stessa pagina: l'email, se c'è un account da confermare, parte dopo la risposta.
        return Response::seeOther(AccountUrls::to(AccountUrls::REGISTER_SENT, $returnTo));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, EmailForm $form, ReturnPath $returnTo): array
    {
        return ['csrf' => CsrfToken::of($request)->value, 'form' => $form, 'returnTo' => $returnTo, 'alert' => null];
    }
}
