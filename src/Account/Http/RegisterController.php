<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\DeliveryStatus;
use FourStreet\Account\Form\RegistrationForm;
use FourStreet\Account\Notice;
use FourStreet\Account\PasswordPolicy;
use FourStreet\Account\Registration;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\Session\Visitor;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Security\Token;

/**
 * /account/registrati/: il modulo di registrazione. Dopo l'invio, sempre la stessa pagina "Controlla la posta"
 * e lo stesso cookie SignupBrowser, che l'email abbia già un account o no.
 */
final readonly class RegisterController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private Registration $registration,
        private PasswordPolicy $policy,
        private SignupBrowser $signup,
    ) {}

    public function __invoke(Request $request): Response
    {
        $submitted = $request->method === 'POST';
        $returnTo = $submitted ? ReturnPath::fromBody($request) : ReturnPath::fromQuery($request);
        if (Visitor::of($request)->isLoggedIn()) {
            return Response::seeOther($returnTo->value);
        }
        if (!$submitted) {
            return $this->pages->render('register', $this->data($request, RegistrationForm::blank(), $returnTo));
        }

        $form = RegistrationForm::fromRequest($request, $this->policy);
        if (!$form->isValid()) {
            return $this->pages->render('register', $this->data($request, $form, $returnTo), 422);
        }

        $browser = Token::generate();
        $delivery = $this->registration->register(
            $form->fullName(),
            $form->emailAddress(),
            $form->password(),
            $returnTo,
            $request->remoteAddress,
            $browser->hash(),
        );

        return match ($delivery->status) {
            DeliveryStatus::Throttled => $this->pages->tooManyAttempts('register', $this->data($request, $form, $returnTo), $delivery->retryAfter),
            DeliveryStatus::Failed => Response::seeOther(AccountUrls::to(AccountUrls::REGISTER_SENT, $returnTo, Notice::MailNotSent))
                ->withCookie($this->signup->cookie($browser)),
            DeliveryStatus::Sent => Response::seeOther(AccountUrls::to(AccountUrls::REGISTER_SENT, $returnTo))
                ->withCookie($this->signup->cookie($browser)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, RegistrationForm $form, ReturnPath $returnTo): array
    {
        return ['csrf' => CsrfToken::of($request)->value, 'form' => $form, 'returnTo' => $returnTo, 'alert' => null];
    }
}
